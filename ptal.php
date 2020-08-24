<?

if (!defined('PTAL_DIR')) define('PTAL_DIR', str_replace('\\','/',dirname(__FILE__)).'/');

/**
 * Ptal template engine
 * 
 * @author Pavel Vladimirov
 * @year 2009
 * @version 0.0.5
 */
class Ptal
{
    /** compile templates directory
     * @var array */
    public $templateDir = 'templates/';

    /** compile templates directory
     * @var array */
    public $compileDir  = 'templates_c/';
    
    /** where assigned template vars are kept
     * @var array */
    private $_tplVars   = array();    
    
    /** associative array of plugins, 
     * needs for include in top of compiled file 
     * @var array */
    private $_plugins = array();
    
    /** array with parsed extends blocks
     * @var array */
    private $_extends = array();
    
    /** if is children - save blocks to $this->_extends
     * else - paste data from blocks
     * @var bool */
    private $_isChildren = false;
    
    /** name of current compile template
     * @var string */
    private $_currentTemplate;
    
    /** names of templates for compile
     */
    private $_templatesForCompile = array();
    
    /** simple_html_dom can change outertext only 1 times
     * because need save php codes to this variable
     */
    private $__outerBlocks = array();
    
    
    /** 
     * Assign variable
     * @var string
     * @var string
     */
    public function assign($name,$value)
    {
        $this->_tplVars[$name] = $value;
    }
    
    /**
     * Fetch template and return result
     * @var string
     * @return string
     */
    public function fetch($template)
    {
        $compileFileName = $this->_compileFileName($template);
        
        /*
         * compile if is not compiled
         */
        if (!$this->_isCompiled($template,$compileFileName))
        {
            $this->_compileTemplate($template,$compileFileName);
        }
        
        /*
         *  execute
         */
        ob_start();
        include($compileFileName);
        $html = ob_get_clean();
        
        /*
         * return result
         */
        return $html;
    }
    
    /**
     *
     */
    private function _isCompiled($template,$compileFileName)
    {
        $fileName = $this->templateDir.$template;
        return (file_exists($compileFileName) && file_exists($fileName) && filemtime($compileFileName) > filemtime($fileName));
    }
    
    /**
     * Template compiler - set prepare values and call _compile method
     * @var string
     * @return string
     */
    private function _compileTemplate($template,$compileFileName)
    { 
        if (in_array($template,$this->_templatesForCompile)) return false;

        $this->_templatesForCompile[] = $template;
        $this->_currentTemplate = $template;

        $fileName = $this->templateDir.$template;
        if (!file_exists($fileName)) die('file '.$fileName.' not exists');

        $this->_compile($fileName,$compileFileName);
        
        return true;
    }

    /**
     * Compiler - compile template and save php code to file
     * @var string
     * @return string
     */    
    private function _compile($fileName,$compileFileName)
    {        
        $body = file_get_contents($fileName);
        // remove comments {*  ... *}
        $body = preg_replace('|\{\*.*\*\}|isU','',$body);
        
        // use simple_html_dom
        include_once(PTAL_DIR.'simple_html_dom.php');
        $html = new simple_html_dom();
        $html->load($body);
        
        
        // parse all elements
        $this->_parseElements($html->root);
        
        
        // 
        $result = $html->save();
        
        $result = $this->_parseOutTagsVars($result);
        
        // get php with plugins includes
        $plugins = $this->_getPlugins();
        
        
        //$result = preg_replace('|<tal:[^>]*>|U','',$result);   // not deleted tags
        //$result = preg_replace('|tal:[^ ]+=".*"|U','',$result); // not deleted attributes
        //$result = preg_replace('|tal:[^ ]+\(.*\)|U','',$result); // not deleted functions
        
        // remove <tal: ... > and </tal: ... >
        // but not remove symbol "->"
        $result = preg_replace('|</?tal:.*[^\-]>|U','',$result);
        
        // save compiled file
        $f = fopen($compileFileName,'wb');
        fwrite($f,$plugins.$result);
        fclose($f);
    }
    
    /** 
     * Generate file name for compiled file
     * @var string
     * @return string
     */
    private function _compileFileName($template)
    {
        $pathinfo = pathinfo($template);
        return $this->compileDir.'compiled_'.str_replace(array('/','.'),'_',$pathinfo['dirname']).'_'.$pathinfo['filename'].'.php';
    }
    
    /**
     * Recursively parse all elements
     * @var object
     */
    private function _parseElements($element)
    {
        // loop by childrens
        foreach ((array)$element->childNodes() as $e)
        {
            if (!$e->hasAttribute('tal:literal'))
            {
                // parse childrens
                $this->_parseElements($e);
            
                // parse self
                $this->_parseElement($e);                
            }
        }
    }
    
    private function _callback1($matches)
    {
        return $this->_parseValue($matches[1],false);
    }
    
    private function _callback2($matches)
    {    
        $i=0;
        return $this->_getFunction($matches[1],$i,false);
    }
    
    private function _parseOutTagsVars($html)
    {
        $globalRe = '|\{([\@\$].*)\}|iU';
        $html = preg_replace_callback($globalRe,array($this,'_callback1'),$html);
        $globalRe = '|\{(tal:.*)\}|iU';
        $html = preg_replace_callback($globalRe,array($this,'_callback1'),$html);
        return $html;
    }
        
    /**
     * Parse DOM element
     * @var object
     */
    private function _parseElement($e)
    {          
        $this->_parseAttributes($e);
/*        
        if (!$e->first_child())
        {
            if ($e->innertext) $e->innertext = $this->_parseString($e->innertext,false,false);
            return;
        }

        // парсинг prefix
        $this->_parsePrefix($e);
        
        // парсинг postfix
        $this->_parsePostfix($e);
        */
    }
    
    /** 
     * parse attributes
     */
    private function _parseAttributes(&$e)
    {
  
        $this->__outerBlocks = array();
    
        /**
         *  Приоритет обработки атрибутов, например, tal:define и tal:condition, 
         *  не будет зависеть от того, в каком порядке они записаны внутри тега SPAN. 
         *  Порядок обработки определяется спецификацией TAL:
         *  1. define
         *  2. condition
         *  3. repeat
         *  4. content или replace
         *  5. attributes
         *  6. omit-tag    
         */       
        // выполняются в следующем порядке (составляется php код, оборачивающий тег)
        $attrList = array();
        $attrList[] = 'tal:if';         // 1. условие
        $attrList[] = 'tal:assign';     // 2.1. define 
        $attrList[] = 'tal:include';    // 2.2. include template - нет в спецификации TAL
        $attrList[] = 'tal:foreach';    // 3.1. loop
        $attrList[] = 'tal:for';        // 3.2. loop
        $attrList[] = 'tal:content';    // 4.1 content
        $attrList[] = 'tal:replace';    // 4.2 replace
        $attrList[] = 'tal:attributes'; // 5. attributes
        
        // стандартные атрибуты
        foreach ($attrList as $attr)
        {
            if ($e->hasAttribute($attr))
            {
                $value = $e->getAttribute($attr);
                $e->removeAttribute($attr);
                $this->_parseAttribute($e,$attr,$value);
            }
        }
        
        
        // цикл по всем остальным атрибутам.
        // здесь важен порядок их указания
        foreach ((array)$e->getAllAttributes() as $attr => $value)
        {
            // simple_html_dom не сразу удаляет атрибуты, поэтому нужна проверка на несовпадение с массивом
            if ($this->_isTal($attr) && !in_array($attr,$attrList))
            {
                $value = $e->getAttribute($attr);
                $this->_parseAttribute($e,$attr,$value);
                $e->removeAttribute($attr);
            }
            // parse functions
            elseif (substr($e->tag,0,4) != 'tal:' && (strpos($value,'tal:')!==false || strpos($value,'{@') !== false || strpos($value,'{$') !== false))
            {
                $value = $this->_parseString($value,false,true);       
                $e->setAttribute($attr,$value);
            }
        }    
        
        $e->outertext = $this->__outerBlocks[0].$e->outertext.$this->__outerBlocks[1];        
        $this->__outerBlocks = array();
    }
    
    /**
     * Parse attribute
     * @var object - element object
     * @var string - attribute name
     * @var string - attribute value
     */
    private function _parseAttribute(&$e,$name,$value)
    {
        $command = $this->_parseCommandName($name);
        
        // trim + remove double spaces
        $value = trim(preg_replace('| {2,}|i',' ',$value));
            
        // operation
        switch($command)
        {
            // tal:content
            case 'content':
                $e->innertext = $this->_parseValue($value);
                break;
                
            // tal:assign
            case 'assign':
                $values = $this->_parseValues($value,true);
                $res = '';
                foreach ((array)$values as $var => $val)
                    $res .= '<?php $this->_tplVars[\''.$var.'\']='.$val.';?>';
                //$e->outertext = $res.$e->outertext;
                //$this->__outerBlocks[0] = $res.$this->__outerBlocks[0];
                $this->__outerBlocks[0] .= $res;
                break;
        
            // tal:if
            case 'if':
                $value = $this->_parseExpression($value,true);
                /*$e->outertext = '<?php if('.$value.'):?>'.$e->outertext.'<?php endif;?>';*/
                $this->__outerBlocks[0] .= '<?php if('.$value.'):?>';
                $this->__outerBlocks[1] = '<?php endif;?>'.$this->__outerBlocks[1];
                break;                

            //tal:include
            case 'include':
                $curTemplate = $this->_currentTemplate;
                $values = $this->_parseValues($value,true);
                /*$e->outertext = '?> '.$e->outertext;*/
                $res = '?> ';
                
                if ($template = str_replace('"','',$values['file']))
                {
                    $compileFileName = $this->_compileFileName($template);
                    // чтобы не было зацикливания при рекурсии
                    if (!in_array($template,$this->_templatesForCompile))
                    {
                        $this->_compileTemplate($template,$compileFileName);
                    }
                    //$e->outertext = 'include("'.$compileFileName.'");'.$e->outertext;
                    $res = 'include("'.$compileFileName.'");'.$res;
                }
                unset($values['file']);
                
                foreach ($values as $var=>$val)
                {
                    //$e->outertext = '$this->_tplVars[\''.$var.'\']='.$val.';'.$e->outertext;
                    $res = '$this->_tplVars[\''.$var.'\']='.$val.';'.$res;
                }
                /*$e->outertext = '<?php '.$e->outertext;*/
                $res = '<?php '.$res;
                $this->__outerBlocks[0] .= $res;
                $this->_currentTemplate = $curTemplate;
                break;
                
            //tal:foreach
            case 'foreach':
                /*$e->outertext = '<?php $ptal[\'index\']=0; foreach((array)'.$this->_parseExpression($value,false).'):?>'.$e->outertext.'<?php $ptal[\'index\']++; endforeach;?>';*/
                $this->__outerBlocks[0] .= '<?php $ptal[\'index\']=0; foreach((array)'.$this->_parseExpression($value,false).'):?>';
                $this->__outerBlocks[1] = '<?php $ptal[\'index\']++; endforeach;?>'.$this->__outerBlocks[1];
                break;
                
            //tal:for
            case 'for':
                /*$e->outertext = '<?php for('.$this->_parseExpression($value,false).'):?>'.$e->outertext.'<?php endfor;?>';*/
                $this->__outerBlocks[0] .= '<?php for('.$this->_parseExpression($value,false).'):?>';
                $this->__outerBlocks[1] = '<?php endfor;?>'.$this->__outerBlocks[1];
                break;
                
            //tal:attributes
            case 'attributes':
                $values = $this->_parseValues($value,false);
                foreach ((array)$values as $var => $val)
                {
                    if ($var) $e->setAttribute($var,$val);
                }
                break;
                
            case 'omit-tag':
                //$e->outertext = preg_replace('','',$e->outertext);
                break;
                
            //tal:block and other
            default:
                $this->_parseBlock($e,$command,$value);
        }
    }    
    
    /**
     * Parse value
     * @var string
     * @return string
     */    
    private function _parseExpression($value)
    {
        $re = "|@([\w]+)|";
        $to = '$this->_tplVars[\'$1\']';
        return preg_replace($re,$to,$value);
    }

    /**
     * Parse value
     * @var string
     * @var string
     * @return string
     */    
    private function _parseString($value,$quotes=false,$functions=true)
    {
        // remove empty expressions {} 
        //$value = str_replace('{}','',$value); 
        $result = '';
        $isExpression = false;
        //$isstring = ($value{0}=='{') ? false : true;
        
        $i=0;
        while ($i<strlen($value))
        {
        
            // expression
            if ($value{$i} == '{' && $value{$i+1} != ' ' && $value{$i+1} != "\n" && $value{$i+1} != "\r") 
            {
                $i++;$str = '';
                while ($i<strlen($value) && $value{$i} != '}')
                {
                    $str .= $value{$i};
                    $i++;
                }
                if ($str) 
                {
                    if ($quotes) $result .= '".'.$this->_parseExpression($str).'."';
                    else         $result .= '<?php echo '.$this->_parseExpression($str).';?>';
                }
                
                $i++;
                continue;
            }
            // function
            if ($functions && substr($value,$i,4)=='tal:')
            {
                $result .= $this->_getFunction($value,$i,$quotes);   
                continue;
            }
            // expression without {
            if ($value{$i} == '@' || $value{$i} == '$' || substr($value,$i,4)=='tal:')
            {
                $str = $value{$i};$i++;
                while ($i<strlen($value) && $value{$i} != ' ' &&  $value{$i} != "\r" && $value{$i} != "\n")
                {
                    $str .= $value{$i};
                    $i++;
                }
                if ($str) 
                {
                    if ($quotes) $result .= '".'.$this->_parseExpression($str).'."';
                    else         $result .= '<?php echo '.$this->_parseExpression($str).';?>';
                }
                continue;
            }
       
            // text
            $result .= $value{$i};
            $i++;
        }
        
        
        
        if ($quotes && !is_numeric($result))
        {
            $result = '"'.$result.'"';
            $result = str_replace(array('"".','.""'),'',$result);
        }
        
        return $result;
    }
    
    
    /**
     *
     */
    private function _parseModifier($value,$modifier)
    {
    
        // explode data by :
        $params = explode(':',$modifier);
        
        // get first element
        $modifier = array_shift($params);
        
        switch ($modifier)
        {
            case '':
                break;
                
            default:
                $pluginName = 'ptal_modifier_'.$modifier;
                $this->_addPlugin($pluginName);
                $value = $pluginName.'('.$value.($params ?','.implode(',',$params):'').')';
        }
        
        return $value;
    }
    
    /**
     * Parse modifiers
     */
    private function _parseModifiers($value,$modifiers)
    {
        foreach ($modifiers as $modifier)
        {
            $value = $this->_parseModifier($value,$modifier);
        }        
        return $value;
    }
    
    
    function _getFunction($value,&$i,$quotes=false)
    {
        if (substr($value,$i,4) !='tal:') return;
        $result = '';

        $i+=4; $name = $params = $string = '';
        while ($i<strlen($value) && $value[$i] != '(')
        {
            $name .= $value{$i}; $i++;
        }
        $i++;
        while ($i<strlen($value) && $value{$i} != ')')
        {
            $params .= $value{$i}; $i++;
        }
        $i++;
        while ($i<strlen($value) && $value{$i} != ';')
        {
            $string .= $value{$i}; $i++;
        }
        $i++;
        
        if ($quotes) $result .= '".'.$this->_parseFunction($name,$params,$string).'."';
        else         $result .= $this->_parseFunction($name,$params,$string);

        return $result;
    }
            
    
    /**
     * return string with php code
     */
    private function _parseFunction($name,$params,$string)
    {
        switch ($name)
        {
            // tal:if
            case 'if':
                $params = $this->_parseExpression($params);
                $val = $this->_parseString($string,false,false);
                $result = '<?php if('.$params.'):?>'.$val.'<?php endif;?>';
                break;

            default:
                $pluginName = 'ptal_function_'.$name;
                $this->_addPlugin($pluginName);
                $params = $this->_parseParams($params);
                $val = $this->_parseString($string,true,false);
                $result = '<?php echo '.$pluginName.'('.$this->_formatParams($params).','.$val.',$this)'.'?>';
            
        }
        return $result;
    }
    
    /**
     * a=bla bla bla,b=2,c=3
     */
    private function _parseParams($value,$sep1=',',$sep2='=')
    {
        $pieces = explode($sep1,$value);
        $params = array();
        foreach ((array)$pieces as $piece)
        {
            $piece = trim($piece);
            if (strpos($piece,$sep2) !== false)
            {
                $var = substr($piece,0,strpos($piece,$sep2));
                $val = substr($piece,strpos($piece,$sep2)+1);
                $params[$var] = $this->_parseString($val,true,false);
            }
            else
            {
                $params[] = $piece;
            }
        }
        return $params;
    }
    
    /**
     *
     */
    function _formatParams($params)
    {
        $res = 'array(';
        $comma = false;        
        foreach ((array)$params as $var=>$val)
        {
            if ($comma) $res .= ','; else $comma = true;
            if (is_numeric($var)) $res .= $var.'=>'.$val;
            else $res .= "'".$var."'".'=>'.$val;
        }
        $res .= ')';
        return $res;
    }
    
    
    /**
     *
     */
    private function _parseBlock(&$e,$name,$value)
    {
        $values = $this->_parseValues($value,true);
        $params = $this->_formatParams($values);
        $pluginName = 'ptal_block_'.$name;
        $this->_addPlugin($pluginName);
        $plugin = $pluginName.'('.$params.',ob_get_clean(),$this)';
        /*$e->outertext = '<?php ob_start()?>'.$e->outertext.'<?php echo '.$plugin.'?>';*/
        $this->__outerBlocks[0] .= '<?php ob_start()?>';
        $this->__outerBlocks[1] = '<?php echo '.$plugin.'?>'.$this->__outerBlocks[1];
    }
    
    
    /**
     * Parse value method
     * @var string
     * @return string
     */
    private function _parseValue($value,$quotes=false)
    {
        $value = trim($value);
        {
            // explode data by '|'
            $modifiers = explode('|',$value);
            
            // get first element
            $value = array_shift($modifiers);
            
            if ($modifiers)
            {
                // parse expression
                $value = $this->_parseString($value,true,true);            
                $value = $this->_parseModifiers($value,$modifiers);
                if ($quotes) $value = '".'.$value.'."';
                else $value = '<?php echo '.$value.'?>';
            }
            else
            {
                // parse expression
                $value = $this->_parseString($value,$quotes,true);            
            }
        }
        return $value;
    }
    
   
    /** 
     * Parse values and return array with parsed values
     * @var string
     * @return array
     */  
    private function _parseValues($values,$quotes = false)
    {
        $result = array();
        $values = explode(';',$values);
        foreach ((array)$values as $value)
        {
            $value = trim($value);
            $var = substr($value,0,strpos($value,' '));
            $val = substr($value,strpos($value,' ')+1);
            if ($var)
            {
                $result[$var] = $this->_parseValue($val,$quotes);
            }
        }
        return $result;
    }
    
    /**
     * Add plugin to $this->_plugins
     * это нужно для проверки , чтобы один плагин подключить только раз
     * @var string
     */
    private function _addPlugin($pluginName)
    {
        $fileName = PTAL_DIR.'plugins/'.$pluginName.'.php';
        if (!file_exists($fileName)) die('file '.$fileName.' not found');
    
        if (!is_array($this->_plugins[$this->_currentTemplate]))
            $this->_plugins[$this->_currentTemplate] = array();
        
        if (!in_array($fileName,$this->_plugins[$this->_currentTemplate]))
            $this->_plugins[$this->_currentTemplate][] = $fileName;
    }
    
    /**
     * Add plugins to template
     * @var object
     */
    private function _getPlugins()
    {    
        $result = '';
        if (is_array($this->_plugins[$this->_currentTemplate]))
        {    
            $result = "\r\n?>";
            foreach ((array)$this->_plugins[$this->_currentTemplate] as $fileName)
            {
                $result = "    \r\n".'require_once("'.$fileName.'");'.$result;
            }
            $result = '<?php '.$result;
        }
        return $result;
    }
    
    /**
     * @var string
     * @return bool
     */
    private function _isTal($value)
    {
        return (substr($value,0,4) == 'tal:');  
    }
    
    /**
     * @var string
     * @return string
     */
    private function _parseCommandName($attr)
    {
        // tal:attr
        return substr($attr,4);
    }
    
}
