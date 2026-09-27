(() => {
  const $ = id => document.getElementById(id);
  const admin = document.body.dataset.role === 'Admin';
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const names = {account:'My Account',dashboard:'Overview',campaigns:'Campaigns',appointments:'Registrations',users:'Users',categories:'Categories',inventory:'Blood Inventory',transactions:'Transactions',messages:'Contact Inbox',reports:'Reports'};
  const statuses = {campaigns:['Draft','Published','Active','Closed'],appointments:['Pending','Confirmed','Checked-In','Completed','Deferred','Cancelled','No-Show'],users:['Active','Suspended','Deactivated','Pending'],inventory:['Available','Reserved','Expired','Disposed'],messages:['New','Replied']};
  let view = new URLSearchParams(location.search).get('view') || 'dashboard', page = 1, pages = 1, sequence = 0, editing = null, categories = [];
  if (!names[view] || (!admin && ['users','categories'].includes(view))) view = 'dashboard';
  function el(tag,text,className) { const node=document.createElement(tag); if(text!==undefined)node.textContent=text??''; if(className)node.className=className; return node; }
  function notice(message,error=false) { const node=el('div',message,'alert '+(error?'alert-danger':'alert-success')); $('portal-notice').replaceChildren(node); }
  async function api(path,method='GET',body) {
    const response=await fetch('api/index.php/'+path,{method,credentials:'same-origin',headers:{'Accept':'application/json',...(method!=='GET'?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{})},...(body?{body:JSON.stringify(body)}:{})});
    let result; try { result=await response.json(); } catch { throw new Error('The server returned an unreadable response. Please try again.'); }
    if(!response.ok)throw new Error(result.message||'Request failed.'); return result;
  }
  function filters() { const params=new URLSearchParams(new FormData($('filters'))); for(const [key,value] of [...params])if(!value)params.delete(key); params.set('page',page); if(view==='campaigns')params.set('scope','management'); return params; }
  function table(headers,rows) {
    const wrapper=el('div',undefined,'table-responsive'), table=el('table',undefined,'table table-hover bg-white'), head=el('thead'), tr=el('tr');
    headers.forEach(h=>{const th=el('th',h);th.scope='col';tr.append(th);});head.append(tr);table.append(head);const body=el('tbody');
    rows.forEach(row=>{const tr=el('tr');row.forEach(value=>{const td=el('td');td.append(value instanceof Node?value:document.createTextNode(value??'—'));tr.append(td);});body.append(tr);});
    table.append(body);wrapper.append(table);return wrapper;
  }
  function button(text,action,kind='outline-primary') { const button=el('button',text,'btn btn-sm btn-'+kind);button.type='button';button.addEventListener('click',action);return button; }
  async function mutate(path,method,body) { try { const result=await api(path,method,body);notice(result.message);await load(); } catch(error){notice(error.message,true);} }
  function actions(record) {
    const box=el('div',undefined,'record-actions');
    if(admin&&['campaigns','users','categories'].includes(view)) {
      box.append(button('Edit',()=>openEditor(view,record)));
      const id=record[view==='campaigns'?'campaign_id':view==='users'?'user_id':'category_id'];
      box.append(button(view==='users'?'Deactivate':'Delete',()=>{if(confirm(view==='users'?'Deactivate this account? Historical records will remain.':'Delete this record? Referenced records cannot be deleted.'))mutate(view+'/'+id,'DELETE');},'outline-danger'));
    }
    if(view==='appointments') {
      const id=record.appointment_id;
      if(record.appointment_status==='Pending')box.append(button('Confirm',()=>mutate('appointments/'+id,'PUT',{appointment_status:'Confirmed'})));
      if(record.appointment_status==='Confirmed') {
        box.append(button('Check in',()=>mutate('appointments/'+id,'PUT',{appointment_status:'Checked-In'})));
        box.append(button('No-show',()=>mutate('appointments/'+id,'PUT',{appointment_status:'No-Show'})));
      }
      if(record.appointment_status==='Checked-In')box.append(button('Record outcome',()=>openEditor('donations',record)));
      if(['Pending','Confirmed'].includes(record.appointment_status))box.append(button('Cancel',()=>{const reason=window.prompt('Reason for cancellation (required):');if(reason!==null)mutate('appointments/'+id,'DELETE',{cancellation_reason:reason});},'outline-danger'));
    }
    if(view==='messages')box.append(button('Read / update',()=>openEditor('messages',record)));
    return box;
  }
  function renderRecords(rows) {
    if(!rows.length){$('records').replaceChildren(el('p','No records match your filters.','alert alert-light border'));return;}
    const definitions={campaigns:[['Campaign','Venue / Event date','Category','Status','Actions'],r=>[r.title,r.location_venue+' · '+r.campaign_date,r.category_name,r.campaign_status+' / '+r.registration_status,actions(r)]],users:[['Name','Email','Role','Status','Actions'],r=>[r.first_name+' '+r.last_name,r.email,r.role_name,r.account_status,actions(r)]],categories:[['Category','Description','Actions'],r=>[r.name,r.description,actions(r)]],appointments:[['Reference / Donor','Campaign','Donation date / Time','Status','Cancellation reason','Actions'],r=>['#'+r.appointment_id+' · '+r.donor_name,r.title,r.campaign_date+' '+r.scheduled_time_slot,r.appointment_status,r.cancellation_reason||'—',actions(r)]],inventory:[['Batch','Blood type','Units','Collection','Expiry','Status'],r=>[r.inventory_id,r.blood_type,r.units_available,r.collection_date,r.expiration_date,r.inventory_status]],transactions:[['Reference','Type','Blood type','Units','Date','Notes'],r=>[r.transaction_id,r.transaction_type,r.blood_type,r.units_transacted,r.transaction_timestamp,r.transaction_notes]],messages:[['From','Subject','Email','Date','Status','Actions'],r=>[r.sender_name,r.subject,r.email,r.received_at,r.message_status,actions(r)]]};
    const [headers,mapper]=definitions[view];$('records').replaceChildren(table(headers,rows.map(mapper)));
  }
  function renderReport(data) {
    const grid=el('div',undefined,'row g-3');
    [['Total registrations',data.total],['Pending',data.counts.Pending],['Completed',data.counts.Completed],['Cancelled',data.counts.Cancelled]].forEach(([label,value])=>{const col=el('div',undefined,'col-6 col-xl-3'),card=el('article',undefined,'card p-3');card.append(el('div',label,'small text-secondary'),el('div',String(value),'metric-value'));col.append(card);grid.append(col);});$('summary').replaceChildren(grid);
    $('monthly-data').replaceChildren(table(['Month','Registrations'],data.monthly.map(r=>[r.month,r.total])));
    $('popular').replaceChildren(data.popular.length?table(['Campaign','Registrations'],data.popular.map(r=>[r.title,r.total])):el('p','No registrations yet.'));
    $('activity').replaceChildren(data.activity.length?table(['Action','Record','Staff','Time'],data.activity.map(r=>[r.action_performed,r.affected_table+' #'+r.target_record_id,r.actor,r.log_timestamp])):el('p','No recorded activities yet.'));
    const canvas=$('monthly-chart'),ctx=canvas.getContext('2d'),rows=data.monthly.slice(-12);canvas.width=800;canvas.height=260;ctx.clearRect(0,0,800,260);ctx.font='14px sans-serif';
    if(!rows.length){ctx.fillStyle='#53647e';ctx.fillText('No registrations to chart yet.',20,50);return;}
    const max=Math.max(1,...rows.map(r=>Number(r.total))),width=720/rows.length;
    rows.forEach((row,i)=>{const height=Number(row.total)/max*170,x=40+i*width;ctx.fillStyle='#385d8e';ctx.fillRect(x,210-height,width*.65,height);ctx.fillStyle='#192a4d';ctx.fillText(String(row.total),x,200-height);ctx.save();ctx.translate(x,235);ctx.rotate(-.25);ctx.fillText(row.month,0,0);ctx.restore();});
  }
  function renderAccount() {
    const card=el('article',undefined,'card p-4 workspace-account');
    card.append(el('h2','Account overview','h4'),el('p','Your staff workspace account details.'),el('p','Name: '+document.body.dataset.name),el('p','Email: '+document.body.dataset.email),el('p','Role: '+document.body.dataset.role));
    $('records').replaceChildren(card);
  }
  async function load() {
    const request=++sequence,reportView=['dashboard','reports'].includes(view),accountView=view==='account';$('view-title').textContent=names[view];
    document.querySelectorAll('[data-view]').forEach(link=>{link.classList.toggle('active',link.dataset.view===view);if(link.dataset.view===view)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');});
    $('report').hidden=!reportView;$('summary').hidden=!reportView;$('pagination').hidden=reportView||view==='categories'||accountView;$('filters').hidden=view==='categories'||accountView;
    $('create-record').hidden=!admin||!['campaigns','users','categories'].includes(view);$('create-record').textContent='New '+({campaigns:'campaign',users:'user',categories:'category'}[view]||'record');
    if(accountView){$('summary').hidden=true;$('report').hidden=true;renderAccount();return;}
    $('records').replaceChildren(el('p','Loading…','text-secondary'));$('records').setAttribute('aria-busy','true');
    try {
      const params=filters();
      if(reportView){const result=await api('reports?'+params);if(request!==sequence)return;renderReport(result.data);$('records').replaceChildren();$('export-report').href='api/index.php/reports?'+params+'&format=csv';}
      else {const result=await api(view+'?'+params);if(request!==sequence)return;renderRecords(result.data);pages=result.meta?.pages||1;page=result.meta?.page||1;$('page-info').textContent='Page '+page+' of '+pages+' · '+(result.meta?.total??result.data.length)+' records';$('previous').disabled=page<=1;$('next').disabled=page>=pages;}
    }catch(error){if(request===sequence){notice(error.message,true);$('records').replaceChildren(el('p','Unable to load records. Use Apply filters to retry.'));}}
    finally{if(request===sequence)$('records').setAttribute('aria-busy','false');}
  }
  function configureFilters(){const select=$('status');select.replaceChildren(new Option('All statuses',''));(statuses[view]||[]).forEach(status=>select.add(new Option(status,status)));select.disabled=!statuses[view];$('query').disabled=['dashboard','reports','transactions','account'].includes(view);$('sort').disabled=['dashboard','reports','account'].includes(view);}
  document.querySelectorAll('[data-view]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();view=link.dataset.view;page=1;$('filters').reset();configureFilters();history.pushState(null,'',link.href);load();}));
  window.addEventListener('popstate',()=>{view=new URLSearchParams(location.search).get('view')||'dashboard';if(!names[view])view='dashboard';page=1;configureFilters();load();});
  $('filters').addEventListener('submit',event=>{event.preventDefault();if($('from').value&&$('to').value&&$('from').value>$('to').value){notice('From date must not be after To date.',true);return;}page=1;load();});
  $('filters').addEventListener('reset',()=>{setTimeout(()=>{page=1;load();},0);});
  $('previous').addEventListener('click',()=>{if(page>1){page--;load();}});$('next').addEventListener('click',()=>{if(page<pages){page++;load();}});
  $('print-report').addEventListener('click',()=>window.print());
  function addField(name,label,type='text',value='',options=null,required=true,readonly=false,max=null) {
    const wrapper=el('div',undefined,type==='textarea'?'col-12':'col-md-6'),id='field-'+name,l=el('label',label,'form-label');l.htmlFor=id;
    const input=el(options?'select':type==='textarea'?'textarea':'input',undefined,options?'form-select':'form-control');if(!options&&type!=='textarea')input.type=type;
    if(options){input.add(new Option('Choose…',''));options.forEach(option=>input.add(new Option(typeof option==='string'?option:option.label,typeof option==='string'?option:option.value)));}
    input.id=id;input.name=name;input.required=required;input.readOnly=readonly;if(max)input.maxLength=max;input.value=value??'';if(type==='number')input.min='1';if(type==='textarea')input.rows=4;wrapper.append(l,input);$('editor-fields').append(wrapper);return input;
  }
  async function openEditor(resource,record={}) {
    editing={resource,id:record[{campaigns:'campaign_id',users:'user_id',categories:'category_id',messages:'message_id'}[resource]],appointment_id:record.appointment_id};
    $('editor-fields').replaceChildren();$('form-error').replaceChildren();$('editor-title').textContent=(resource==='donations'?'Record donation outcome':editing.id?'Edit ':'Create ')+(resource==='donations'?'':names[resource]);
    if(resource==='campaigns') {
      try{categories=(await api('categories')).data;}catch(error){notice(error.message,true);return;}
      addField('title','Campaign name','text',record.title,null,true,false,100);addField('location_venue','Donation location','text',record.location_venue,null,true,false,150);
      addField('campaign_date','Event date','date',record.campaign_date);addField('start_time','Start time','time',record.start_time?.slice(0,5));addField('end_time','End time','time',record.end_time?.slice(0,5));
      addField('registration_closes_at','Registration deadline (optional)','datetime-local',record.registration_closes_at?.replace(' ','T').slice(0,16),null,false);
      addField('campaign_status','Publication status','text',record.campaign_status||'Draft',['Draft','Published','Active','Closed']);addField('total_slots','Capacity (staff only)','number',record.total_slots||30);
      addField('category_id','Category (optional)','text',record.category_id,categories.map(c=>({label:c.name,value:c.category_id})),false);addField('description','Description','textarea',record.description,null,false,false,3000);
    }
    if(resource==='users') {
      addField('first_name','First name','text',record.first_name,null,true,false,50);addField('last_name','Last name','text',record.last_name,null,true,false,50);addField('email','Email','email',record.email,null,true,false,100);
      addField('role_name','Role','text',record.role_name||'Donor',['Admin','Staff','Donor']);addField('account_status','Status','text',record.account_status||'Active',['Active','Suspended','Deactivated','Pending']);addField('contact_number','Contact number','tel',record.contact_number,null,false,false,20);
      const password=addField('password',editing.id?'New password (leave blank to keep current)':'Initial password','password','',null,!editing.id,false,72);password.minLength=12;password.autocomplete='new-password';
    }
    if(resource==='categories'){addField('name','Category name','text',record.name,null,true,false,80);addField('description','Description','textarea',record.description,null,false,false,255);}
    if(resource==='messages'){addField('sender','Sender','text',record.sender_name+' · '+record.email,null,false,true);addField('subject','Subject','text',record.subject,null,false,true);addField('message_body','Message','textarea',record.message_body,null,false,true);addField('message_status','Status (mark Replied after responding)','text',record.message_status,['New','Replied']);}
    if(resource==='donations') {
      addField('appointment_id','Registration reference','number',record.appointment_id,null,true,true);
      const outcome=addField('clinical_outcome','Staff-recorded outcome','text','',['Completed','Deferred']);
      addField('blood_type_collected','Blood type','text','',['A+','A-','B+','B-','AB+','AB-','O+','O-']);
      const volume=addField('volume_ml','Volume collected (ml)','number',''),expiry=addField('expiration_date','Unit expiry date (staff supplied)','date',''),eligible=addField('estimated_eligible_date','Next eligible date (staff supplied)','date',''),reason=addField('deferral_reason','Deferral reason','textarea','',null,false,false,1000);
      addField('medical_notes','Staff notes','textarea','',null,false,false,2000);
      outcome.addEventListener('change',()=>{const completed=outcome.value==='Completed';[volume,expiry,eligible].forEach(input=>{input.required=completed;input.disabled=!completed;});reason.required=outcome.value==='Deferred';});
    }
    $('editor').showModal();$('editor-fields').querySelector('input,select,textarea')?.focus();
  }
  $('create-record').addEventListener('click',()=>openEditor(view));$('close-editor').addEventListener('click',()=>$('editor').close());$('cancel-editor').addEventListener('click',()=>$('editor').close());
  $('record-form').addEventListener('submit',async event=>{event.preventDefault();if(!$('record-form').reportValidity())return;$('save-record').disabled=true;try{const data=Object.fromEntries(new FormData($('record-form')));const result=await api(editing.resource+(editing.id?'/'+editing.id:''),editing.id?'PUT':'POST',data);$('editor').close();notice(result.message);await load();}catch(error){$('form-error').replaceChildren(el('p',error.message,'alert alert-danger'));}finally{$('save-record').disabled=false;}});
  configureFilters();load();
})();
