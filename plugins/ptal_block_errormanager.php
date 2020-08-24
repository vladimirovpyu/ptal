<?

function ptal_block_errormanager($params,$content,$ptal)
{
    $result = "";
    $errors = ErrorManager::getErrors();
    if (is_array($errors[$params['context']])) 
    {
        foreach ($errors[$params['context']] as $errormsg)
        {
            $ptal->assign("errormsg",$errormsg);
            $result .= $ptal->fetch("errormsg.tal");
        }
    }
    return $result;
}
