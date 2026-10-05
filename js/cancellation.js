/* Shared accessible cancellation form; rejected submissions retain the reason. */
window.openCancellation = ({reference,onSubmit}) => {
  const dialog=document.createElement('dialog');dialog.className='cancellation-dialog';
  const form=document.createElement('form'),title=document.createElement('h2'),label=document.createElement('label'),reason=document.createElement('textarea'),help=document.createElement('p'),error=document.createElement('p'),buttons=document.createElement('div'),back=document.createElement('button'),submit=document.createElement('button');
  title.textContent='Cancel registration';title.id='cancel-title';dialog.setAttribute('aria-labelledby',title.id);
  help.textContent='Registration '+reference+'. This releases your place and preserves your cancellation in the history.';
  label.textContent='Reason for cancellation';reason.id='cancellation-reason';label.htmlFor=reason.id;reason.required=true;reason.minLength=3;reason.maxLength=1000;reason.rows=4;reason.placeholder='For example: I am unable to attend on the scheduled date.';
  const hint=document.createElement('small');hint.id='cancel-help';hint.textContent='3–1,000 characters. Review your reason before confirming.';reason.setAttribute('aria-describedby',hint.id);
  error.className='form-error';error.setAttribute('role','alert');back.type='button';back.textContent='Keep registration';back.className='btn-navy';submit.type='submit';submit.textContent='Confirm cancellation';submit.className='btn-danger';
  back.onclick=()=>dialog.close();buttons.className='dialog-actions';buttons.append(back,submit);form.append(title,help,label,reason,hint,error,buttons);dialog.append(form);document.body.append(dialog);
  let busy=false;dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});dialog.addEventListener('close',()=>dialog.remove());
  form.addEventListener('submit',async e=>{e.preventDefault();if(busy||!form.reportValidity())return;busy=true;submit.disabled=back.disabled=true;error.textContent='';try{await onSubmit(reason.value.trim());dialog.close();}catch(err){error.textContent=err.message;error.tabIndex=-1;error.focus();}finally{busy=false;submit.disabled=back.disabled=false;}});
  dialog.showModal();reason.focus();
};
