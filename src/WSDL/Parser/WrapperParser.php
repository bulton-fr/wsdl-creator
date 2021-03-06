<?php
/**
 * Copyright (C) 2013-2015
 * Piotr Olaszewski <piotroo89@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
namespace WSDL\Parser;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use WSDL\Types\Arrays;
use WSDL\Types\Object;

/**
 * WrapperParser
 *
 * @author Piotr Olaszewski <piotroo89@gmail.com>
 */
class WrapperParser
{
    protected $_wrapperClass;
	protected $typeName;
    protected $dynamicProperties;
    protected $className;
    /**
     * @var ComplexTypeParser[]
     */
    protected $_complexTypes;

    public function __construct($wrapperClass)
    {
        $this->className = $wrapperClass;
        
        $this->_wrapperClass = new ReflectionClass($wrapperClass);
		$this->detectTypeName();
    }
	
	protected function detectTypeName()
	{
		$docComment = $this->_wrapperClass->getDocComment();
		$matches    = [];
		
		preg_match('#@typeName (\w+)#', $docComment, $matches);
		if(isset($matches[1]))
		{
			$this->typeName = $matches[1];
			return;
		}
		
		$this->typeName = str_replace('\\', '', $this->className);
	}
    
    protected function dynamicProperties()
    {
        $staticMethods = $this->_wrapperClass->getMethods(ReflectionMethod::IS_STATIC);
        
        if($staticMethods === array())
        {
            return array();
        }
        
        $className = $this->className;
        foreach($staticMethods as $method)
        {
            $methodName = $method->name;
            
            if($methodName !== 'dynamicProperties')
            {
                continue;
            }
            
            return $className::dynamicProperties();
        }
        
        return array();
    }
	
	public function getTypeName()
	{
		return $this->typeName;
	}

    public function parse()
    {
        $publicFields = $this->_wrapperClass->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($publicFields as $field) {
            $this->_makeComplexType($field->getName(), $field->getDocComment());
        }
        
        $dynamicFields = $this->dynamicProperties();
        foreach($dynamicFields as $field)
        {
            $this->_makeComplexType($field['name'], $field['type']);
        }
    }

    protected function _makeComplexType($name, $docComment)
    {
        if (preg_match('#@type (\w*)\[\]#', $docComment, $matches)) {
            $type = $matches[1];
            $strategy = 'array';
        } else {
            preg_match('#@type (\w+)#', $docComment, $matches);
            if (isset($matches[1])) {
                $type = trim($matches[1]);
                $strategy = trim($matches[1]);
            } else {
                $type = 'void';
                $strategy = 'void';
            }
        }

        switch ($strategy) {
            case 'object':
                $this->_complexTypes[] = new Object($type, $name, $this->getComplexTypes());
                break;
            case 'wrapper':
                $this->_complexTypes[] = $this->_createWrapperObject($type, $name, $docComment);
                break;
            case 'array':
                $this->_complexTypes[] = $this->_createArrayObject($type, $name, $docComment);
                break;
            default:
                $this->_complexTypes[] = new ComplexTypeParser($type, $name);
                break;
        }
    }
    
    protected function _createWrapperObject($type, $name, $docComment)
    {
        $wrapper = $this->wrapper($type, $docComment);
        $object = null;
        if ($wrapper->getComplexTypes()) {
            $object = new Object($type, $name, $wrapper->getComplexTypes());
        }
        return new Object($type, $name, $object);
    }

    protected function _createArrayObject($type, $name, $docComment)
    {
        $object = null;
        if ($type == 'wrapper') {
            $complex = $this->wrapper($type, $docComment)->getComplexTypes();
            $object = new Object($type, $name, $complex);
        } elseif ($this->isComplex($type)) {
            $complex = $this->getComplexTypes();
            $object = new Object($type, $name, $complex);
        }
        return new Arrays($type, $name, $object);
    }

    public function getComplexTypes()
    {
        return $this->_complexTypes;
    }
    
    public function wrapper(&$type, $docComment)
    {
        if (!$this->isComplex($type)) {
            throw new WrapperParserException("This attribute is not complex type.");
        }
        preg_match('#@className=(.*?)(?:\s|$)#', $docComment, $matches);
        $className = $matches[1];
        //$type = str_replace('\\', '', $className);
        $wrapperParser = new WrapperParser($className);
        $wrapperParser->parse();
		$type = $wrapperParser->getTypeName();
        return $wrapperParser;
    }

    public function isComplex($type)
    {
        return in_array($type, array('object', 'wrapper'));
    }
}
