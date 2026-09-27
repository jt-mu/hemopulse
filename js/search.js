let campaignPage=1,campaignPages=1,campaignSequence=0,searchTimer;
function filterEvents(){campaignPage=1;clearTimeout(searchTimer);searchTimer=setTimeout(loadCampaignPage,180);}
async function loadCampaignPage(){
  const sequence=++campaignSequence,params=new URLSearchParams({page:campaignPage,limit:6,q:document.getElementById('eventSearch').value,status:document.getElementById('statusFilter').value,sort:document.getElementById('campaign-sort').value});
  if(params.get('status')==='all')params.delete('status');
  const venue=document.getElementById('locationFilter').value;if(venue!=='all')params.set('venue',venue);
  for(const key of ['from','to'])if(document.getElementById('campaign-'+key).value)params.set(key,document.getElementById('campaign-'+key).value);
  const empty=document.getElementById('noEvents'),cards=document.getElementById('eventCards'),tbody=document.getElementById('eventTable');
  const make=(tag,text,className)=>{const el=document.createElement(tag);if(text!==undefined)el.textContent=text;if(className)el.className=className;return el;};
  try{
    const response=await fetch('api/index.php/campaigns?'+params),result=await response.json();if(!response.ok)throw new Error(result.message);if(sequence!==campaignSequence)return;
    cards.replaceChildren();tbody.replaceChildren();
    for(const c of result.data){
      const card=make('article',undefined,'upcoming-card');card.dataset.location=c.location_venue;card.dataset.status=c.registration_status;
      card.append(make('h3',c.title,'upcoming-card-title'));const imageBox=make('div',undefined,'card-img-box sm-img'),image=make('img');image.src='images/about-img.jpg';image.alt='Community blood donation';imageBox.append(image);card.append(imageBox,make('p',c.location_venue),make('div',c.campaign_date,'chip-date'),make('p',c.start_time.slice(0,5)+'–'+c.end_time.slice(0,5)),make('p',c.registration_status,'campaign-status status-'+c.registration_status.toLowerCase()));
      if(c.registration_status==='OPEN'){const link=make('a','Register Now','btn-figma-yellow btn-sm');link.href='dashboard.php?campaign_id='+Number(c.campaign_id);card.append(link);}else{const button=make('button',c.registration_status==='FULL'?'Registration Full':'Registration Closed','btn-figma-yellow btn-sm');button.disabled=true;card.append(button);}cards.append(card);
      const row=make('tr');[c.title,c.campaign_date,c.start_time.slice(0,5)+'–'+c.end_time.slice(0,5),c.location_venue,c.registration_status].forEach(text=>row.append(make('td',text)));tbody.append(row);
    }
    campaignPage=result.meta.page;campaignPages=result.meta.pages;document.getElementById('campaign-page').textContent='Page '+campaignPage+' of '+campaignPages;document.getElementById('campaign-prev').disabled=campaignPage<=1;document.getElementById('campaign-next').disabled=campaignPage>=campaignPages;empty.hidden=result.data.length>0;empty.textContent='No campaigns match these filters.';
  }catch(error){if(sequence!==campaignSequence)return;empty.hidden=false;empty.textContent=error.message||'Campaigns are unavailable. Please try again.';}
}
document.getElementById('campaign-prev').addEventListener('click',()=>{if(campaignPage>1){campaignPage--;loadCampaignPage();}});
document.getElementById('campaign-next').addEventListener('click',()=>{if(campaignPage<campaignPages){campaignPage++;loadCampaignPage();}});
loadCampaignPage();
