(() => {
  const $ = id => document.getElementById(id);
  const admin = document.body.dataset.role === 'Admin';
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const names = {'audit-logs':'Audit Log',screenings:'Eligibility Reviews',newsletter:'Newsletter',account:'My Account',dashboard:'Overview',campaigns:'Campaigns',appointments:'Registrations',users:'Users',categories:'Categories',inventory:'Blood Inventory',transactions:'Transactions',messages:'Contact Inbox',reports:'Reports'};
  const statuses = {screenings:['Eligible','Needs Review','Temporarily Deferred','Not Eligible','Reviewed'],newsletter:['Pending','Subscribed','Unsubscribed'],campaigns:['Draft','Published','Active','Closed'],appointments:['Overdue','Pending','Confirmed','Checked-In','Completed','Deferred','Cancelled','No-Show'],users:['Active','Suspended','Deactivated','Pending'],inventory:['Pending','Available','Reserved','Used','Expired','Discarded','Disposed'],messages:['New','Read','Replied','Closed']};
  let view = new URLSearchParams(location.search).get('view') || 'dashboard', page = 1, pages = 1, sequence = 0, editing = null, categories = [];
  if (!names[view] || (!admin && ['users','categories','newsletter'].includes(view))) view = 'dashboard';
  function el(tag,text,className) { const node=document.createElement(tag); if(text!==undefined)node.textContent=text??''; if(className)node.className=className; return node; }
  function notice(message,error=false) { window.showNotice('portal-notice',message,error);if(error){$('portal-notice').tabIndex=-1;$('portal-notice').focus();$('portal-notice').scrollIntoView({block:'center',behavior:'smooth'});} }
  async function api(path,method='GET',body) {
    const response=await fetch('api/index.php/'+path,{method,credentials:'same-origin',headers:{'Accept':'application/json',...(method!=='GET'?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{})},...(method!=='GET'?{body:JSON.stringify(body||{})}:{})});
    let result; try { result=await response.json(); } catch { throw new Error('The server returned an unreadable response. Please try again.'); }
    if(!response.ok)throw new Error(result.message||'Request failed.'); return result;
  }
  function filters() { const params=new URLSearchParams(new FormData($('filters'))); for(const [key,value] of [...params])if(!value)params.delete(key); params.set('page',page); if(view==='campaigns')params.set('scope','management'); return params; }
  function table(headers,rows) {
    const wrapper=el('div',undefined,'table-responsive'), table=el('table',undefined,'table table-hover bg-white'), head=el('thead'), tr=el('tr');
    headers.forEach(h=>{const th=el('th',h);th.scope='col';tr.append(th);});head.append(tr);table.append(head);const body=el('tbody');
    rows.forEach(row=>{const tr=el('tr');row.forEach(value=>{const td=el('td');let content=value instanceof Node?value:document.createTextNode(value??'—');if(typeof value==='string'&&value.length>120){const detail=el('details');detail.append(el('summary',value.slice(0,90)+'… View details'),el('p',value));content=detail;}if(typeof value==='string'&&Object.values(statuses).flat().includes(value)){content=el('span',value,'hp-status');content.dataset.state=value.toLowerCase();}td.append(content);tr.append(td);});body.append(tr);});
    table.append(body);wrapper.append(table);return wrapper;
  }
  function button(text,action,kind='outline-primary') { const button=el('button',text,'btn btn-sm btn-'+kind);button.type='button';button.addEventListener('click',async()=>{if(button.disabled)return;button.disabled=true;try{await action();}finally{button.disabled=false;}});return button; }
  async function mutate(path,method,body) { try { const result=await api(path,method,body);notice(result.message);await load(); } catch(error){notice(error.message,true);} }
  function actions(record) {
    const box=el('div',undefined,'record-actions');
    if(admin&&['campaigns','users','categories'].includes(view)) {
      box.append(button('Edit',()=>openEditor(view,record)));
      const id=record[view==='campaigns'?'campaign_id':view==='users'?'user_id':'category_id'];
      if(view!=='users')box.append(button('Delete',()=>{if(confirm('Delete this record? Referenced records cannot be deleted.'))return mutate(view+'/'+id,'DELETE');},'outline-danger'));
    }
    if(view==='appointments') {
      const id=record.appointment_id;
      const started=new Date(record.campaign_date+'T'+record.scheduled_time_slot)<=new Date();const eventDay=record.campaign_date===new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Manila',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
      if(record.appointment_status==='Pending')box.append(button('Confirm',()=>mutate('appointments/'+id,'PUT',{appointment_status:'Confirmed'})));
      if(record.appointment_status==='Confirmed') {
        if(eventDay)box.append(button('Check in',()=>mutate('appointments/'+id,'PUT',{appointment_status:'Checked-In'})));
        if(started)box.append(button('No-show',()=>mutate('appointments/'+id,'PUT',{appointment_status:'No-Show'})));
      }
      if(record.appointment_status==='Checked-In')box.append(button('Record outcome',()=>openEditor('donations',record)));
      if(['Pending','Confirmed'].includes(record.appointment_status))box.append(button('Cancel',()=>window.openCancellation({reference:record.public_reference,onSubmit:async reason=>{const result=await api('appointments/'+id,'DELETE',{cancellation_reason:reason});notice(result.message);await load();}}),'outline-danger'));
    }
    if(view==='screenings')box.append(button('Review',()=>openEditor('screenings',record)));
    if(view==='inventory'&&!['Used','Discarded','Disposed'].includes(record.inventory_status))box.append(button('Process',()=>openEditor('inventory',record)));
    if(view==='messages')box.append(button('Read / update',()=>openEditor('messages',record)));
    return box;
  }
  function transactionDetails(record) {
    const detail=el('details');detail.append(el('summary',(record.transaction_notes||'View details').slice(0,60)+'…'));
    for(const [label,value] of [['Donor',record.donor_reference],['Campaign',record.campaign],['Current unit status',record.inventory_status],['Notes',record.transaction_notes]])detail.append(el('p',label+': '+(value||'—')));
    return detail;
  }
  function renderRecords(rows) {
    if(!rows.length){$('records').replaceChildren(el('p','No records match your filters.','alert alert-light border'));return;}
    const definitions={'audit-logs':[['Action','Record','Staff','Time','Details'],r=>[r.action_performed,r.affected_table+' #'+r.target_record_id,r.actor||'System',r.log_timestamp,changeDetails(r.details_json)]],screenings:[['Donor reference','Donor','Result','Review date','Reviewed by','Actions'],r=>[r.public_reference,r.donor_name,r.outcome,r.deferred_until,r.reviewer,actions(r)]],newsletter:[['Email','Status','Delivery','Requested','Confirmed'],r=>[r.email,r.subscription_status==='Pending'?'Pending Confirmation':r.subscription_status,r.delivery_status,r.requested_at,r.confirmed_at]],campaigns:[['Campaign','Venue / Event date','Category','Status','Actions'],r=>[r.title,r.location_venue+' · '+r.campaign_date,r.category_name,r.campaign_status+' / '+r.registration_status,actions(r)]],users:[['Reference','Name','Email','Role','Status','Notice delivery','Actions'],r=>[r.public_reference,r.first_name+' '+r.last_name,r.email,r.role_name,r.account_status,r.notice_delivery||'—',actions(r)]],categories:[['Category','Description','Actions'],r=>[r.name,r.description,actions(r)]],appointments:[['Reference / Donor','Campaign','Donation date / Time','Status','Cancellation reason','Actions'],r=>[r.public_reference+' · '+r.donor_name,r.title,r.campaign_date+' '+r.scheduled_time_slot,r.appointment_status,r.cancellation_reason||'—',actions(r)]],inventory:[['Unit','Blood type','Units','Volume per unit (mL)','Collection','Expiry','Status','Donor','Source','Processed by','Actions'],r=>[r.public_reference,r.blood_type,r.units_available,r.volume_ml_per_unit,r.collection_date,r.expiration_date,r.inventory_status,r.donor_reference,r.source,r.processed_by||'System',actions(r)]],transactions:[['Reference','Event','Blood / Units','Date','Responsible','Details'],r=>[r.public_reference,({Addition:'Blood unit added',Deduction:'Blood unit used',Adjustment:'Inventory status changed',Expiration:'Blood unit expired',Disposal:'Blood unit discarded'}[r.transaction_type]||r.transaction_type),r.blood_type+' / '+r.units_transacted,r.transaction_timestamp,r.actor||'System',transactionDetails(r)]],messages:[['From','Subject','Email','Date','Status','Actions'],r=>[r.sender_name,r.subject,r.email,r.received_at,r.message_status,actions(r)]]};
    const [headers,mapper]=definitions[view];$('records').replaceChildren(table(headers,rows.map(mapper)));
  }
  function changeDetails(raw){if(!raw)return '—';try{const d=JSON.parse(raw);if(d.before?.campaign_date&&d.after?.date)return 'Date: '+d.before.campaign_date+' → '+d.after.date+'; status: '+d.before.campaign_status+' → '+d.after.status;return Object.entries(d).map(([k,v])=>k.replaceAll('_',' ')+': '+(v&&typeof v==='object'?JSON.stringify(v):(v??'—'))).join('; ')||'—';}catch{return '—';}}
  function renderReport(data) {
    const grid=el('div',undefined,'metric-grid');
    [['Total registrations',data.total],['Pending',data.counts.Pending],['Completed',data.counts.Completed],['Cancelled',data.counts.Cancelled]].forEach(([label,value])=>{const col=el('div',undefined,'metric-cell'),card=el('article',undefined,'card p-3');card.append(el('div',label,'small text-secondary'),el('div',String(value),'metric-value'));col.append(card);grid.append(col);});$('summary').replaceChildren(grid);
    for(const [label,value] of Object.entries(data.metrics||{})){const card=el('article',undefined,'card p-3');card.append(el('div',label.replaceAll('_',' '),'small text-secondary'),el('strong',String(value),'metric-value'));grid.append(card);}
    if(data.stock?.length)$('summary').append(table(['Blood type','Pending','Available','Reserved','Expired','Used','Discarded','Available volume (mL)','Stock indicator'],data.stock.map(r=>[r.blood_type,r.pending,r.units,r.reserved,r.expired,r.used,r.discarded,r.available_volume_ml,r.status])));
    $('monthly-data').replaceChildren(table(['Month','Registrations'],data.monthly.map(r=>[r.month,r.total])));
    $('popular').replaceChildren(data.popular.length?table(['Campaign','Registrations'],data.popular.map(r=>[r.title,r.total])):el('p','No registrations yet.'));
    $('activity').replaceChildren(data.activity.length?table(['Action','Record','Staff','Time','Change details'],data.activity.map(r=>[r.action_performed,r.affected_table+' #'+r.target_record_id,r.actor,r.log_timestamp,changeDetails(r.details_json)])):el('p','No recorded activities yet.'));
    const canvas=$('monthly-chart'),ctx=canvas.getContext('2d'),rows=data.monthly.slice(-12);canvas.width=800;canvas.height=260;ctx.clearRect(0,0,800,260);ctx.font='14px sans-serif';
    if(!rows.length){ctx.fillStyle='#53647e';ctx.fillText('No registrations to chart yet.',20,50);return;}
    const max=Math.max(1,...rows.map(r=>Number(r.total))),width=720/rows.length;
    rows.forEach((row,i)=>{const height=Number(row.total)/max*170,x=40+i*width;ctx.fillStyle='#385d8e';ctx.fillRect(x,210-height,width*.65,height);ctx.fillStyle='#192a4d';ctx.fillText(String(row.total),x,200-height);ctx.save();ctx.translate(x,235);ctx.rotate(-.25);ctx.fillText(row.month,0,0);ctx.restore();});
  }
  function renderAccount() {
    const content=$('staff-account-template').content.cloneNode(true);$('records').replaceChildren(content);const upload=$('records').querySelector('.profile-upload'),file=upload.querySelector('input[type=file]'),submit=upload.querySelector('button[type=submit]');const updateUpload=()=>{submit.disabled=!file.files.length;};file.addEventListener('change',updateUpload);updateUpload();return;

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
  function configureFilters(){
    const report=['dashboard','reports'].includes(view),dated=['campaigns','appointments','screenings','inventory','transactions','messages','newsletter','audit-logs'].includes(view);
    const basis={campaigns:'Event',appointments:'Event',screenings:'Screening',inventory:'Collection',transactions:'Transaction',messages:'Received',newsletter:'Requested','audit-logs':'Action'}[view]||'Event';document.querySelector('[for=from]').textContent=basis+' date from';document.querySelector('[for=to]').textContent=basis+' date to';
    const select=$('status');select.replaceChildren(new Option('All statuses',''));(statuses[view]||[]).forEach(status=>select.add(new Option(status,status)));select.disabled=!statuses[view];
    $('query').disabled=report||view==='account';
    for(const id of ['from','to']){$(id).disabled=!(dated||report);$(id).parentElement.hidden=$(id).disabled;}
    const options={users:[['newest','Newest accounts'],['name','Name A–Z']],campaigns:[['date','Event date'],['newest','Latest event first'],['name','Campaign A–Z']],appointments:[['newest','Newest registration'],['date','Appointment date'],['name','Donor A–Z']],screenings:[['newest','Newest screening'],['date','Screening date']],inventory:[['newest','Newest unit'],['date','Expiry date']],transactions:[['newest','Newest transaction'],['date','Transaction date']],messages:[['newest','Newest message'],['date','Received date']],newsletter:[['newest','Newest request'],['date','Requested date']],'audit-logs':[['newest','Newest action'],['date','Action date']]};
    $('sort').replaceChildren(...(options[view]||[]).map(([value,label])=>new Option(label,value)));$('sort').disabled=!options[view];
    for(const id of ['query','status','sort'])$(id).parentElement.hidden=$(id).disabled;
    $('audit-extra').hidden=view!=='audit-logs';$('audit-actor').disabled=$('audit-action').disabled=view!=='audit-logs';
  }
  document.querySelectorAll('[data-view]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();$('portal-notice').replaceChildren();window.scrollTo(0,0);view=link.dataset.view;page=1;$('filters').reset();configureFilters();history.pushState(null,'',link.href);load();}));
  window.addEventListener('popstate',()=>{$('portal-notice').replaceChildren();view=new URLSearchParams(location.search).get('view')||'dashboard';if(!names[view])view='dashboard';page=1;configureFilters();load();});
  $('filters').addEventListener('submit',event=>{event.preventDefault();if($('from').value&&$('to').value&&$('from').value>$('to').value){notice('From date must not be after To date.',true);return;}page=1;load();});
  $('filters').addEventListener('reset',()=>{setTimeout(()=>{page=1;load();},0);});
  $('previous').addEventListener('click',()=>{if(page>1){page--;load();}});$('next').addEventListener('click',()=>{if(page<pages){page++;load();}});
  $('print-report').addEventListener('click',()=>window.print());
  function addField(name,label,type='text',value='',options=null,required=true,readonly=false,max=null) {
    const wrapper=el('div',undefined,type==='textarea'?'col-12':'col-md-6'),id='field-'+name,l=el('label',label,'form-label');l.htmlFor=id;
    const input=el(options?'select':type==='textarea'?'textarea':'input',undefined,options?'form-select':'form-control');if(!options&&type!=='textarea')input.type=type;
    if(options){input.add(new Option('Choose…',''));options.forEach(option=>input.add(new Option(typeof option==='string'?option:option.label,typeof option==='string'?option:option.value)));}
    input.id=id;input.name=name;input.required=required;input.readOnly=readonly;if(max){input.maxLength=max;input.title='Maximum '+max+' characters.';}input.value=value??'';if(type==='number')input.min='1';if(type==='textarea')input.rows=4;wrapper.append(l,input);if(max&&type!=='password')wrapper.append(el('small','Maximum '+max+' characters.','text-secondary'));if(type==='number')wrapper.append(el('small',name==='volume_ml'?'1–1,000 mL. Record the measured volume.':'Enter a whole number of at least 1.','text-secondary'));if(type==='password'){const group=el('div',undefined,'editor-password-input');const toggle=button('Show',()=>{input.type=input.type==='password'?'text':'password';toggle.textContent=input.type==='password'?'Show':'Hide';toggle.setAttribute('aria-pressed',String(input.type==='text'));toggle.setAttribute('aria-label',(input.type==='password'?'Show ':'Hide ')+label);});toggle.setAttribute('aria-controls',id);toggle.setAttribute('aria-pressed','false');toggle.setAttribute('aria-label','Show '+label);group.append(input,toggle);wrapper.append(group);}
    if(type==='tel'){input.pattern='09[0-9]{9}';input.maxLength=11;input.placeholder='09171234567';}
    $('editor-fields').append(wrapper);return input;
  }
  async function openEditor(resource,record={}) {
    editing={resource,id:record[{campaigns:'campaign_id',users:'user_id',categories:'category_id',messages:'message_id',screenings:'eligibility_id',inventory:'inventory_id'}[resource]],appointment_id:record.appointment_id};
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
      const statusReason=addField('status_reason','Reason for status change','textarea','',null,false,false,1000);const accountStatus=$('field-account_status');accountStatus.addEventListener('change',()=>{statusReason.required=accountStatus.value!==(record.account_status||'Active');});
      $('editor-fields').append(el('p','Active permits sign-in; Pending awaits activation; Suspended temporarily restricts access; Deactivated closes access while preserving history. A status change requires a reason and creates an email notice.'));
      const password=addField('password',editing.id?'New password (leave blank to keep current)':'Initial password','password','',null,!editing.id,false,72);password.minLength=12;password.autocomplete='new-password';
      addField('confirm_password','Confirm password','password','',null,!editing.id,false,72);
      $('editor-fields').append(el('p','Use 12–72 bytes, a letter and a number or symbol. Avoid common or repeated passwords.'));
    }
    if(resource==='categories'){addField('name','Category name','text',record.name,null,true,false,80);addField('description','Description','textarea',record.description,null,false,false,255);}
    if(resource==='messages'){
      const replies=await api('message-replies/'+record.message_id);
      if(replies.followups?.length)addField('visitor_followups','Visitor follow-up messages','textarea',replies.followups.map(r=>r.received_at+'\n'+r.message_body).join('\n\n'),null,false,true);
      $('editor-fields').append(el('p','The reply email includes a private conversation link for the visitor to respond here. Delivery status is recorded below; preview mode sends no email.'));
      if(replies.data.length)addField('reply_history','Previous replies','textarea',replies.data.map(r=>r.replied_at+' · '+r.author+' · '+r.delivery_status+'\n'+r.reply_body).join('\n\n'),null,false,true);
addField('sender','Sender','text',record.sender_name+' · '+record.email,null,false,true);addField('subject','Subject','text',record.subject,null,false,true);addField('message_body','Message','textarea',record.message_body,null,false,true);addField('reply_body','Reply by email','textarea','',null,false,false,5000);addField('message_status','Action','text','Read',['Read','Replied','Closed']);}
    if(resource==='screenings'){
      addField('donor','Donor','text',record.public_reference+' · '+record.donor_name,null,false,true);
      let answers;try{answers=JSON.parse(record.answers_json);}catch{answers={};}
      addField('answers','Screening answers (staff only)','textarea',Object.entries(answers).map(([k,v])=>k.replaceAll('_',' ')+': '+v).join('\n'),null,false,true);
      addField('outcome','Decision (Reviewed requests a new assessment)','text',record.outcome,['Eligible','Temporarily Deferred','Reviewed','Not Eligible']);
      addField('review_notes','Notes visible to donor','textarea',record.review_notes,null,true,false,2000);
      addField('deferred_until','Deferred until / next review date','date',record.deferred_until,null,false);
    }
    if(resource==='inventory'){
      const transitions={Pending:['Available','Discarded'],Available:['Reserved','Used','Discarded'],Reserved:['Available','Used','Discarded'],Expired:['Discarded']};
      addField('inventory_status','New status','text','',transitions[record.inventory_status]||[]);
      addField('notes','Processing / testing decision and reason','textarea','',null,true,false,1000);
      $('editor-fields').append(el('p','Mark Available only after required testing and processing are complete.'));
    }
    if(resource==='donations') {
      addField('registration_reference','Registration reference','text',record.public_reference,null,true,true);
      const outcome=addField('clinical_outcome','Staff-recorded outcome','text','',['Completed','Deferred']);
      const blood=addField('blood_type_collected','Blood type collected','text','',['A+','A-','B+','B-','AB+','AB-','O+','O-']);
      const volume=addField('volume_ml','Volume collected (ml)','number',''),expiry=addField('expiration_date','Unit expiry date (staff supplied)','date',''),eligible=addField('deferred_until','Deferral review date (only if deferred)','date','',null,false),reason=addField('deferral_reason','Deferral reason','textarea','',null,false,false,1000);
      addField('medical_notes','Staff notes','textarea','',null,false,false,2000);
      outcome.addEventListener('change',()=>{const completed=outcome.value==='Completed';[blood,volume,expiry].forEach(input=>{input.required=completed;input.disabled=!completed;});reason.required=outcome.value==='Deferred';eligible.required=outcome.value==='Deferred';eligible.disabled=completed;});
    }
    $('editor').showModal();$('editor-fields').querySelector('input,select,textarea')?.focus();
  }
  $('create-record').addEventListener('click',()=>openEditor(view));$('close-editor').addEventListener('click',()=>$('editor').close());$('cancel-editor').addEventListener('click',()=>$('editor').close());
  $('record-form').addEventListener('submit',async event=>{event.preventDefault();if($('save-record').disabled)return;if(!$('record-form').reportValidity())return;$('save-record').disabled=true;try{const data=Object.fromEntries(new FormData($('record-form')));if(editing.resource==='messages'){editing.request_key ||= crypto.randomUUID();data.request_key=editing.request_key;}if(editing.resource==='donations')data.appointment_id=editing.appointment_id;if(data.password){if(!window.validAccountPassword(data.password))throw new Error('Use 12–72 bytes with a letter and a number or symbol; avoid common or repeated passwords.');if(data.password!==data.confirm_password)throw new Error('Passwords must match.');}const result=await api(editing.resource+(editing.id?'/'+editing.id:''),editing.id?'PUT':'POST',data);$('editor').close();notice(result.message);await load();}catch(error){$('form-error').replaceChildren(el('p',error.message,'alert alert-danger'));}finally{$('save-record').disabled=false;}});
  configureFilters();load();
})();
