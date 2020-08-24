<?

function ptal_block_headers($params, $content, &$ptal)
{

    
    if (is_null($content)) {
        return;
    }
    
    global $_PTAL_HEAD;
    
    preg_match_all('|\[(.*)\](.*)\[/.*\]|isU',$content,$matches);
    
    $blockNames = $matches[1];
    $blocks     = $matches[2];
      
    foreach ($blockNames as $i=>$blockName)
    {
        if (!is_array($_PTAL_HEAD[$blockName])) $_PTAL_HEAD[$blockName] = array();
        if (!in_array($blocks[$i],$_PTAL_HEAD[$blockName])) $_PTAL_HEAD[$blockName][] = $blocks[$i];
        
        $html = '';        
        foreach ($_PTAL_HEAD[$blockName] as $line)
        {
            $html .= $line."\r\n";
        }
        $ptal->assign($blockName,$html);
    }
    return;
}
