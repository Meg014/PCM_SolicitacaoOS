<?php
require 'vendor/autoload.php'; require 'config/bootstrap.php';
(require 'config/routes.php')(\Cake\Routing\Router::createRouteBuilder('/'));
$view = new \Cake\View\View(new \Cake\Http\ServerRequest());
$element = $view->element('execution_items');
$harness = <<<'JS'
window.errors=[]; window.addEventListener('error', e=>errors.push(e.message));
window.fetch=async ()=>({ok:true,redirected:false,json:async()=>({results:[{registration:'006783',name:'ABADIO',function_code:'00005',function_name:'Mecânico',label:'006783 - ABADIO - Mecânico'}]})});
JS;
$checks = <<<'JS'
const wait = ms => new Promise(resolve=>setTimeout(resolve,ms));
(async()=>{
 let checks=0; const check=(ok,msg)=>{if(!ok) throw Error(msg); checks++;};
 try {
  const root=document.getElementById('request-items'); const add=document.getElementById('add-item');
  check(!root.querySelector('[data-search]').required,'initial optional');
  add.click(); check(root.children.length===2,'add');
  const row=root.lastElementChild; const search=row.querySelector('[data-search]');
  check(search.required,'added required');
  search.value='AB'; search.dispatchEvent(new Event('input',{bubbles:true})); await wait(400);
  check(row.querySelectorAll('[role=option]').length===1,'dynamic lookup');
  search.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true}));
  search.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true}));
  check(row.querySelector('[data-selected]').value==='006783','keyboard selection');
  search.value='ABAD'; search.dispatchEvent(new Event('input',{bubbles:true}));
  check(row.querySelector('[data-selected]').value==='','edited selection invalidated'); await wait(400);
  row.querySelector('[role=option]').click(); check(search.value==='006783 - ABADIO - Mecânico','touch selection');
  row.querySelector('[data-item-field=service_date]').value='2026-11-03';
  row.querySelector('[data-item-field=start_time]').value='08:00';
  row.querySelector('[data-item-field=end_time]').value='09:00';
  row.querySelector('textarea').value='Revisão do motor';
  row.querySelector('textarea').dispatchEvent(new Event('input',{bubbles:true}));
  check(document.querySelector('form').checkValidity(),'complete row valid');
  root.firstElementChild.querySelector('.remove-item').click();
  check(root.firstElementChild.querySelector('[data-selected]').name==='items[0][employee_registration]','renumber');
  root.firstElementChild.querySelector('.remove-item').click(); check(root.children.length===0,'remove all');
  add.click(); check(root.children.length===1,'add after empty');
  const ids=[...document.querySelectorAll('[id]')].map(x=>x.id); check(ids.length===new Set(ids).size,'unique ids');
  check(document.documentElement.scrollWidth<=innerWidth,'no horizontal page overflow');
  const cols=getComputedStyle(root.firstElementChild).gridTemplateColumns.split(' ').length;
  check(cols===(innerWidth>1100?6:innerWidth>650?4:3),'responsive columns');
  check(root.querySelector('input[type=date]').getBoundingClientRect().height>=44,'touch height');
  check(errors.length===0,errors.join(';'));
  document.getElementById('result').textContent='PASS '+checks+' checks width='+innerWidth;
 } catch(error) { document.getElementById('result').textContent='FAIL '+error.message; }
})();
JS;
$html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.file_get_contents('webroot/css/app.css').'</style></head><body><main class="container"><section class="request-form execution-form"><form><fieldset id="os-disposition"><input type="radio" name="os_disposition" value="MANTER_ABERTA" checked></fieldset><fieldset id="execution-initial">'.$element.'</fieldset></form></section><pre id="result">PENDING</pre></main><script>'.$harness.'</script><script>'.file_get_contents('webroot/js/request-form.js').'</script><script>'.file_get_contents('webroot/js/employee-autocomplete.js').'</script><script>'.$checks.'</script></body></html>';
file_put_contents('tmp/employee-browser.html',$html);
foreach ([360, 768, 1366] as $width) {
    $frame = '<!doctype html><html><head><meta charset="utf-8"></head><body>'
        . '<iframe id="frame" src="employee-browser.html" style="width:' . $width . 'px;height:800px;border:0"></iframe>'
        . '<pre id="frame-result">PENDING</pre><script>setInterval(()=>{const result=document.getElementById("frame").contentDocument.getElementById("result");'
        . 'if(result)document.getElementById("frame-result").textContent=result.textContent;},100);</script></body></html>';
    file_put_contents('tmp/employee-browser-' . $width . '.html', $frame);
}
echo 'Browser fixture ready', PHP_EOL;
