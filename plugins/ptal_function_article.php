<?

function ptal_function_article($params,$value,$ptal)
{
    //return 'http://'.$params['action'].'/'.$params['page'];
    return LinkManager::link($params);
}
