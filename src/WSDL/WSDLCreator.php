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
namespace WSDL;

use Exception;
use WSDL\Parser\ClassParser;
use WSDL\Service\Service;
use WSDL\Utilities\Strings;
use WSDL\XML\Styles\RpcLiteral;
use WSDL\XML\Styles\Style;
use WSDL\XML\XMLGenerator;

/**
 * WSDLCreator
 *
 * @author Piotr Olaszewski <piotroo89@gmail.com>
 */
class WSDLCreator
{
    protected $_class;
    protected $_location;
    /**
     * @var ClassParser
     */
    protected $_classParser;
    
    /**
     * Name, with namespace, of parser class to use.
     * @var string $classParserName
     */
    protected $classParserName = '\WSDL\Parser\ClassParser';
    protected $_namespace = 'http://example.com/';
    /**
     * @var Style
     */
    protected $_bindingStyle;
    /**
     * @var string
     */
    protected $_locationSuffix;

    public function __construct($class, $location, $locationSuffix = 'wsdl')
    {
        $this->_class = $class;
        $this->_location = $location;
        $this->_locationSuffix = $locationSuffix;
        $this->_bindingStyle = new RpcLiteral();
        $this->_parseClass();
    }

    protected function _parseClass()
    {
        $this->_classParser = new $this->classParserName($this->_class);
        $this->_classParser->parse();
    }
    
    public function setClassParser($parserName)
    {
        if(!is_string($parserName))
        {
            throw new Exception('ParserClass redefined must be a string');
        }
        
        $this->classParserName = $parserName;
        $this->_parseClass();
        
        return $this;
    }

    public function renderWSDL()
    {
        header("Content-Type: text/xml");
        $xml = new XMLGenerator($this->_class, $this->_namespace, $this->_location);
        $xml
            ->setWSDLMethods($this->_classParser->getMethods())
            ->setBindingStyle($this->_bindingStyle)
            ->generate();
        $xml->render();
    }

    public function setNamespace($namespace)
    {
        $this->_namespace = $this->_addSlashAtEndIfNotExists($namespace);
        return $this;
    }

    public function getNamespaceWithSanitizedClass()
    {
        return Strings::sanitizedNamespaceWithClass($this->_namespace, $this->_class);
    }

    protected function _addSlashAtEndIfNotExists($namespace)
    {
        return rtrim($namespace, '/') . '/';
    }

    public function setBindingStyle(Style $style = null)
    {
        if (!$style) {
            throw new Exception('Incorrect binding style.');
        }
        $this->_bindingStyle = $style;
        return $this;
    }

    public function renderWSDLService()
    {
        $headers = apache_request_headers();
        if (empty($headers['Content-Type']) || !preg_match('#xml#i', $headers['Content-Type'])) {
            $newService = new Service($this->_location, $this->getWsdlLocation(), $this->_classParser->getMethods());
            $newService->render($this->_class, $this->getNamespaceWithSanitizedClass());
        }
    }

    public function getLocation()
    {
        return $this->_location;
    }

    public function getWsdlLocation()
    {
        return $this->_location . '?' . $this->_locationSuffix;
    }
}
