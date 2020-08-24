# ptal
Simple template engine for PHP (TAL based)

<h2>Быстрый старт</h2>


<h3>
	index.php</h3>
<pre class="brush:php;ruler:true;highlight: [1];">&lt;?php
require(&#39;ptal.php&#39;);
// создаем обработчик шаблонов
$ptal = new Ptal;
// устанавливаем директории
$ptal-&gt;template_dir = &#39;templates/&#39;;
$ptal-&gt;compile_dir  = &#39;templates_c/&#39;;
// передаем данные в шаблонизатор
$ptal-&gt;assign(&#39;hello&#39;,&#39;Hello&#39;);
echo $ptal-&gt;fetch(&#39;index.tal&#39;);
</pre>
<h3>
	templates/index.tal</h3>
<pre class="brush:xml;highlight: [1];">&lt;!DOCTYPE html PUBLIC &quot;-//W3C//DTD XHTML 1.0 Strict//EN&quot; &quot;http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd&quot;&gt;
&lt;html&gt;
  &lt;head&gt;
  &lt;/head&gt;
  &lt;body&gt;
    &lt;span&gt;{@hello}&lt;/span&gt;
    &lt;span tal:content=&quot;Ptal!&quot;&gt;&lt;/span&gt;
  &lt;/body&gt;
&lt;/html&gt;</pre>

<h2>Пример создания рекурсивного меню</h2>
<p>
	Пример создания рекурсивного меню (совместимого с <a href="http://jstree.com/">jsTree</a>)</p>
<p>
	&nbsp;</p>
<h4>
	test.php</h4>
<pre class="brush:php;highlight: [1];">&lt;?php
require (&#39;lib/ptal/ptal.php&#39;);

class Item
{
    var $id;
    var $pid;
    var $title;
    var $link;
    
    function __construct($params)
    {
        $this->id    = $params['id'];
        $this->pid   = $params['pid'];
        $this->title = $params['title'];
        $this->link  = $params['link'];
    }
}

$list = array();
$list[] = new Item(array('id'=>1,'pid'=>0,'title'=>'Page1'));
$list[] = new Item(array('id'=>2,'pid'=>1,'title'=>'Sub Page1'));
$list[] = new Item(array('id'=>3,'pid'=>1,'title'=>'Sub Page2'));

$tree = array();
foreach ((array)$list as $item)
{
    $tree[$item->pid][$item->id] = $item;
}

$ptal = new Ptal;

$ptal->assign('tree',$tree);
$ptal->assign('curpage',$list[1]);

echo $ptal->fetch('treemenu.tal');
</pre>

<h4>
	templates/treemenu.tal</h4>
<pre class="brush:xml;">&lt;tal:block tal:if=&quot;!@pid&quot;   tal:assign=&quot;pid 0&quot; /&gt;
&lt;ul tal:if=&quot;isset(@tree[@pid])&quot;&gt;
  &lt;li tal:foreach=&quot;@tree[@pid] as $node&quot; id=&quot;phtml_{$node-&gt;id}&quot;&gt;
    &lt;a class=&quot;item tal:if($node-&gt;id == @curpage-&gt;id) current&quot; href=&quot;{$node-&gt;link}&quot;&gt;{$node-&gt;title}&lt;/a&gt;
    &lt;tal:block tal:if=&quot;@tree[$node-&gt;id]&quot; tal:include=&quot;file treemenu.tal;pid {$node-&gt;id};&quot; /&gt;    
  &lt;/li&gt;
&lt;/ul&gt;
</pre>

<h4>
	Полученный HTML код</h4>
<pre class="brush:xml;">&lt;ul&gt;
  &lt;li id=&quot;phtml_1&quot;&gt;
    &lt;a class=&quot;item &quot; href=&quot;&quot;&gt;Page1&lt;/a&gt;  
&lt;ul&gt;
  &lt;li id=&quot;phtml_2&quot;&gt;
    &lt;a class=&quot;item  current&quot; href=&quot;&quot;&gt;Sub Page1&lt;/a&gt;       
  &lt;/li&gt;&lt;li id=&quot;phtml_3&quot;&gt;
    &lt;a class=&quot;item &quot; href=&quot;&quot;&gt;Sub Page2&lt;/a&gt;       
  &lt;/li&gt;
&lt;/ul&gt;     
  &lt;/li&gt;
&lt;/ul&gt;</pre>
<p>
	&nbsp;</p>
