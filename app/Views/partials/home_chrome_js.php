<?php declare(strict_types=1);
/**
 * @file app/Views/partials/home_chrome_js.php
 * @description 官网外壳共用脚本：顶栏滚动、进度条、回到顶部、移动端菜单、入场动画、数字滚动、年份
 * @author AI
 * @version 0.6.2
 * @date 2026-09-14
 */
?>
<script>
const $=s=>document.querySelector(s), $$=s=>document.querySelectorAll(s);

/* Header 滚动 / 进度条 / 回到顶部 */
const header=$('#header'), bar=$('#progressBar'), backTop=$('#backTop');
let ticking=false;
function onScroll(){
  const y=window.scrollY, h=document.documentElement.scrollHeight-window.innerHeight;
  header.classList.toggle('scrolled', y>40);
  bar.style.width=(h>0? y/h*100:0)+'%';
  backTop.classList.toggle('show', y>600);
  ticking=false;
}
window.addEventListener('scroll',()=>{ if(!ticking){ requestAnimationFrame(onScroll); ticking=true; }},{passive:true});
onScroll();

/* 移动端菜单 */
$('#navToggle').addEventListener('click',()=>document.body.classList.toggle('nav-open'));
$$('.mobile-menu a').forEach(a=>a.addEventListener('click',()=>document.body.classList.remove('nav-open')));
$('.mobile-menu').addEventListener('click',e=>{ if(!e.target.closest('a')) document.body.classList.remove('nav-open'); });

/* 滚动进入动画 */
const io=new IntersectionObserver(es=>{es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}})},{threshold:.12});
$$('.reveal').forEach(el=>io.observe(el));

/* 数字滚动 */
function runCounter(el){
  const t=parseFloat(el.dataset.count), d=parseInt(el.dataset.decimals||0), dur=1800, s=performance.now();
  (function frame(n){
    const p=Math.min((n-s)/dur,1), v=t*(1-Math.pow(1-p,3));
    el.textContent=d? v.toFixed(d): Math.round(v).toLocaleString('en-US');
    if(p<1) requestAnimationFrame(frame);
  })(s);
}
const io2=new IntersectionObserver(es=>{es.forEach(e=>{if(e.isIntersecting){runCounter(e.target);io2.unobserve(e.target);}})},{threshold:.6});
$$('.count').forEach(el=>io2.observe(el));

$('#year').textContent=new Date().getFullYear();

/* 订阅：系统暂无独立订阅表，引导至真实的站内信/注册入口 */
function subscribeNews(){
  const el=$('#nl-email'), tip=$('#nl-tip'), v=(el&&el.value||'').trim();
  const to='/user/register';
  if(v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)){
    tip.textContent='邮箱格式不正确，请重新填写。';
    tip.classList.add('is-warn');
    return;
  }
  tip.classList.remove('is-warn');
  tip.textContent='正在前往注册，注册后即可在会员中心接收物流资讯…';
  window.location.href=to;
}
</script>
