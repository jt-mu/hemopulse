document.addEventListener('DOMContentLoaded', () => {
 const items=[...document.querySelectorAll('.faq-item')], details=[...document.querySelectorAll('details.faq-bar')];
 const closeItems=()=>items.forEach(item=>{item.classList.remove('open');item.querySelector('.faq-question').setAttribute('aria-expanded','false');});
 items.forEach((item,index)=>{const button=item.querySelector('.faq-question'),answer=item.querySelector('.faq-answer'); answer.id='faq-answer-'+index;button.setAttribute('aria-controls',answer.id);button.setAttribute('aria-expanded','false');button.addEventListener('click',()=>{const open=item.classList.contains('open');closeItems();if(!open){item.classList.add('open');button.setAttribute('aria-expanded','true');}});});
 details.forEach(item=>item.addEventListener('toggle',()=>{if(item.open)details.forEach(other=>{if(other!==item)other.open=false;});}));
});
