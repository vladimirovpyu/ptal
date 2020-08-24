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
        $this-&gt;id    = $params[&#39;id&#39;];
        $this-&gt;pid   = $params[&#39;pid&#39;];
        $this-&gt;title = $params[&#39;title&#39;];
        $this-&gt;link  = $params[&#39;link&#39;];
    }
}

$list = array();
$list[] = new Item(array(&#39;id&#39;=&gt;1,&#39;pid&#39;=&gt;0,&#39;title&#39;=&gt;&#39;Page1&#39;));
$list[] = new Item(array(&#39;id&#39;=&gt;2,&#39;pid&#39;=&gt;1,&#39;title&#39;=&gt;&#39;Sub Page1&#39;));
$list[] = new Item(array(&#39;id&#39;=&gt;3,&#39;pid&#39;=&gt;1,&#39;title&#39;=&gt;&#39;Sub Page2&#39;));

$tree = array();
foreach ((array)$list as $item)
{
    $tree[$item-&gt;pid][$item-&gt;id] = $item;
}

$ptal = new Ptal;

$ptal-&gt;assign(&#39;tree&#39;,$tree);
$ptal-&gt;assign(&#39;curpage&#39;,$list[1]);

echo $ptal-&gt;fetch(&#39;treemenu.tal&#39;);
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
