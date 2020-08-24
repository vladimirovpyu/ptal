<?

function ptal_function_link($params,$value,$ptal)
{
    //return 'http://'.$params['action'].'/'.$params['page'];
    return LinkManager::link($params);
}
