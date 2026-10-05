"""Generate schema-based Mermaid and a self-contained, printable ERD/API guide."""
import json
from pathlib import Path
from html import escape

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs'
schema = json.loads((OUT / 'erd-schema.json').read_text(encoding='utf-8-sig'))
legacy = {'blood_requests', 'request_fulfillments', 'inventory_thresholds', 'login_attempts'}
logical = [('users', 'eligibility_checks', 'reviewed_by')]

# Method, route, access, reads, writes/side effects.
apis = [
('GET','session','Public','users, roles; PHP session','Creates/returns session CSRF token'),
('GET','campaigns','Public; management scope: Admin/Staff','campaigns, campaign_categories','None'),
('POST / PUT / DELETE','campaigns[/{id}]','Admin','campaigns, appointments, campaign_categories','campaigns; notifications on reschedule; donor_campaign_selection on delete; audit_logs'),
('GET','categories','Public','campaign_categories','None'),
('POST / PUT / DELETE','categories[/{id}]','Admin','campaign_categories, campaigns','campaign_categories, audit_logs'),
('GET','audit-logs','Admin/Staff','audit_logs, users','None; paginated, actor/action/date filters'),
('GET','users','Admin','users, roles, account_status_notices','None'),
('POST / PUT / DELETE','users[/{id}]','Admin','users, roles','users, account_status_notices, audit_logs; status change queues notice; DELETE deactivates account'),
('GET','appointments','Authenticated; donor sees own','appointments, campaigns, users','None'),
('POST','appointments','Donor','users, roles, eligibility_checks, campaigns, appointments, donor_profiles, donation_records','appointments, campaign_registrations, campaigns capacity, audit_logs'),
('PUT / DELETE','appointments/{id}','Admin/Staff; donor DELETE own only','appointments, campaigns, users','appointments, campaigns capacity on cancellation, notifications, audit_logs'),
('GET','screenings','Admin/Staff','eligibility_checks, users','None'),
('PUT','screenings/{id}','Admin/Staff','eligibility_checks, users, donor_profiles, donation_records, appointments','eligibility_checks, notifications, audit_logs'),
('POST','donations','Admin/Staff','appointments, campaigns, eligibility_checks, donor_profiles, campaign_registrations, donation_records, users','donation_records, appointments, notifications, donor_profiles; Completed adds blood_inventory and inventory_transactions; Deferred updates eligibility_checks; audit_logs'),
('GET','inventory','Admin/Staff','blood_inventory, inventory_transactions, donation_records, appointments, users, campaigns','Expiry detection can update blood_inventory and add inventory_transactions'),
('PUT','inventory/{id}','Admin/Staff','blood_inventory','blood_inventory, inventory_transactions, audit_logs; also expiry detection'),
('GET','transactions','Admin/Staff','inventory_transactions, blood_inventory, donation_records, appointments, users, campaigns','None'),
('GET','messages','Admin/Staff','contact_messages','None'),
('PUT','messages/{id}','Admin/Staff','contact_messages, contact_replies','contact_messages, contact_replies, audit_logs; SMTP or private preview for reply'),
('GET','message-replies/{id}','Admin/Staff','contact_replies, contact_followups, users','None; id is message_id, not reply_id'),
('GET','newsletter','Admin','newsletter_subscriptions','None; management list does not send newsletters'),
('GET','notifications','Authenticated; own only','notifications','None; paginated'),
('PUT','notifications/{id}','Authenticated; own only','notifications','notifications.is_read'),
('GET','reports','Authenticated; donor limited to own','appointments, campaigns; staff additionally users, audit_logs, eligibility_checks, blood_inventory','Staff report can expire inventory; format=csv exports the report'),
]
handlers = [
('POST backend/login_handler.php; POST backend/auth_handler.php','Public login; auth_handler also logout','users, roles, request_limits; PHP sessions'),
('POST backend/otp_handler.php','Public; send_otp / verify_otp','users, roles, request_limits; OTP pending data in PHP session; mail transport'),
('POST backend/eligibility_handler.php','Donor','eligibility_checks, donor_profiles, donation_records, appointments, users, donor_campaign_selection'),
('POST backend/campaign_selection.php','Donor','donor_campaign_selection, eligibility_checks, campaigns, users'),
('POST backend/appointment_handler.php','Donor; action=book_slot','Same shared booking service as POST appointments'),
('POST backend/donation_handler.php','Admin/Staff','Same shared intake service as POST donations'),
('POST backend/profile_handler.php','Authenticated','users; private profile-image files'),
('POST backend/notification_handler.php','Authenticated; own only','notifications'),
('GET / POST contact_thread.php','Submitting browser session or private expiring token; POST requires CSRF','contact_messages, contact_replies, contact_followups, request_limits, audit_logs'),
('POST backend/contact_handler.php','Public','contact_messages, request_limits, audit_logs'),
('POST backend/newsletter_handler.php','Public','newsletter_subscriptions; confirmation mail'),
('GET newsletter_confirm.php; GET newsletter_unsubscribe.php','Public with secret token','newsletter_subscriptions'),
('GET profile_photo.php','Authenticated; own image','users; private profile-image files'),
('backend/api/campaigns.php','Public compatibility listing','campaigns; different response/list ordering from main API'),
('GET backend/api/inventory.php','Admin/Staff compatibility listing','blood_inventory, inventory_transactions; expiry detection'),
('POST backend/api/appointments.php','Donor compatibility booking','Delegates to appointment_handler.php; supports JSON time alias'),
('POST backend/fulfillment_handler.php','Admin/Staff; HTTP 410','Disabled: recipient fulfillment is outside the current donor application'),
]

mermaid = ['erDiagram']
for table, data in schema.items():
    mermaid.append(f'    {table} {{')
    fks = {f['COLUMN_NAME'] for f in data['foreign_keys']}
    for c in data['columns']:
        keys = []
        if c['COLUMN_KEY']=='PRI': keys.append('PK')
        elif c['COLUMN_KEY']=='UNI': keys.append('UK')
        if c['COLUMN_NAME'] in fks: keys.append('FK')
        key = ' '+','.join(keys) if keys else ''
        note = ' "nullable"' if c['IS_NULLABLE']=='YES' else ''
        mermaid.append(f"        {c['DATA_TYPE']} {c['COLUMN_NAME']}{key}{note}")
    mermaid.append('    }')
for child, data in schema.items():
    cols={c['COLUMN_NAME']:c for c in data['columns']}
    for fk in data['foreign_keys']:
        col=cols[fk['COLUMN_NAME']]
        parent='o|' if col['IS_NULLABLE']=='YES' else '||'
        children='o|' if col['COLUMN_KEY'] in ('PRI','UNI') else 'o{'
        mermaid.append(f"    {fk['REFERENCED_TABLE_NAME']} {parent}--{children} {child} : {fk['COLUMN_NAME']}")
# Dotted edge is an application relationship, not an enforced database FK.
mermaid.append('    users o|..o{ eligibility_checks : reviewed_by_application_only')
(OUT/'HEMOPULSE_ERD.mmd').write_text('\n'.join(mermaid)+'\n',encoding='utf-8')

intro = '''# HemoPulse ERD and API map

Generated from the installed MariaDB schema and the current PHP route/service code, October 4, 2026. Includes all 24 installed tables; no application records or credentials are included.

APIs are operations over entities, not database entities themselves. The ERD shows tables and relationships; the API map below shows how endpoints read and change them.

## Reading the ERD

PK = primary key; FK = enforced foreign key; UK = individually unique key. Nullable fields are labelled. A parent end `||` means exactly one and `o|` means zero or one. A child end `o{` means zero or many. Dotted `reviewed_by` is a PHP-managed relationship with no physical foreign key. Solid edges are database foreign keys; they do not mean cascade deletion.

`campaign_registrations.appointment_id`, `donation_records.appointment_id`, `donor_profiles.user_id`, and `donor_campaign_selection.user_id` enforce at most one child per parent. The appointment's compound active-booking unique index is an additional constraint, not a one-to-one donor/campaign relationship. Public reference columns are unique alternate identifiers, not foreign keys.

## Complete physical ERD

'''
notes='''
## API architecture

```mermaid
flowchart LR
    Visitor[Visitor pages] --> Public[Public reads and form handlers]
    Donor[Donor dashboard] --> Own[Own registrations and notifications]
    Staff[Staff workspace] --> Ops[Screenings / intake / inventory / contact]
    Admin[Admin workspace] --> Manage[Users / campaigns / categories / newsletter]
    Public --> Router[PHP API and shared services]
    Own --> Router
    Ops --> Router
    Manage --> Router
    Router --> Auth[Session / role / CSRF guards]
    Auth --> DB[(MariaDB entities in ERD)]
    Router --> Mail[PHPMailer or private preview files]
    Mail --> SMTP[Configured external SMTP server]
```

Google Maps on Contact is a browser embed, separate from the HemoPulse JSON API and database. It does not create campaign records. Web fonts and other browser assets are presentation dependencies, not database relationships.

## Implementation boundaries

- Admin, Staff and Donor are rows in `roles`; they are not separate user tables.
- Inventory provenance follows `donation_records → inventory_transactions → blood_inventory`. There is no direct donation_id column on blood_inventory. Completed intake creates a Pending unit and its Addition transaction; Deferred intake creates no unit and allows blood_type_collected=NULL.
- Eligibility reviewers use `eligibility_checks.reviewed_by → users.user_id` in code; this column has no database FK. It is the dotted ERD edge.
- `audit_logs.affected_table` and `target_record_id` form a generic audit pointer, not enforced foreign keys to every business table.
- Newsletter and contact emails are standalone strings, not user foreign keys. Visitors do not need an account to submit them.
- `blood_requests`, `request_fulfillments`, and `inventory_thresholds` are retained schema features with no active main API mutation routes. The old fulfillment handler returns HTTP 410. `login_attempts` is retained; current throttling uses `request_limits` and hashed identities.
- OTP data, session authentication, CSRF tokens, uploaded profile files, and private .eml previews are not database tables. SMTP/PHPMailer is an external transport, not an entity or HTTP API endpoint.
- GET inventory and staff GET reports perform automatic expiry updates; they are not completely read-only.
- Registration status transitions are Pending → Confirmed → Checked-In → Completed/Deferred; cancellation and No-Show follow the allowed branches. Completed intake rechecks the effective donation/deferral waiting date under the donor lock.
- Reply attempts are saved before SMTP. A stable request_key prevents duplicate sends on retry. Sending/Unknown states require manual transport-log reconciliation; Accepted means server acceptance, not inbox delivery.

## Main JSON API

Base URL: `/hemopulse/api/index.php/`. `{id}` is a positive internal numeric ID. Routes with `[/{id}]` use collection POST and item PUT/DELETE. Responses are JSON except reports with `format=csv`. Session cookies authenticate requests; non-GET main API requests require `X-CSRF-Token`. Public reads are session, campaigns, and categories. Unsupported methods return 405. No standalone GET item routes exist unless listed here.

'''
md=intro+'```mermaid\n'+'\n'.join(mermaid)+'\n```\n'+notes
md+='| Method | Route | Access | Reads | Writes / effects |\n|---|---|---|---|---|\n'
for row in apis: md+='| '+' | '.join(row)+' |\n'
md+='\n## Form handlers and compatibility endpoints\n\nThese are existing PHP endpoints, not additional REST resources. Form mutations generally use a hidden csrf field and may redirect; OTP and compatibility handlers return JSON.\n\n| Endpoint | Access / purpose | Data or service |\n|---|---|---|\n'
for row in handlers: md+='| '+' | '.join(row)+' |\n'
md+='\n## Source and regeneration\n\nSchema: `deployment/schema.sql`, `database/user_flows.sql`, `database/portal.sql`, `database/revision.sql`, `database/migrate.php`, checked against installed metadata. APIs: `api/index.php`, `includes/api_*.php`, `includes/booking.php`, `includes/notifications.php`, and handlers listed above.\n\nRun `php scripts/export_schema.php` to inspect current metadata; save its UTF-8 JSON output to `docs/erd-schema.json`, then run `python scripts/build_erd.py` to rebuild these artifacts. This guide is a schema and code inspection, not an API availability test.\n'
(OUT/'HEMOPULSE_ERD.md').write_text(md,encoding='utf-8')

# Each focused diagram has one parent and up to three children. Shared parent
# connectors are intentional junctions; unrelated paths never cross.
from collections import defaultdict
from PIL import Image, ImageDraw, ImageFont
relations=defaultdict(list)
for child,data in schema.items():
    for fk in data['foreign_keys']:
        relations[fk['REFERENCED_TABLE_NAME']].append((child,fk['COLUMN_NAME'],False))
relations['users'].append(('eligibility_checks','reviewed_by',True))
order=['roles','users','campaign_categories','campaigns','eligibility_checks','appointments','donation_records','blood_inventory','contact_messages','blood_requests']
labels={'roles':'Accounts and roles','users':'User relationships','campaign_categories':'Campaign categories','campaigns':'Campaign registrations and selections','eligibility_checks':'Screening relationships','appointments':'Registration and clinical outcome','donation_records':'Donation provenance','blood_inventory':'Inventory provenance and allocation','contact_messages':'Contact reply history','blood_requests':'Retained recipient request relationships'}
groups=[]
for parent in order:
    edges=relations[parent]
    for start in range(0,len(edges),3):
        suffix=f' · {start//3+1}' if len(edges)>3 else ''
        groups.append((labels[parent]+suffix,parent,edges[start:start+3]))

def diagram(title,parent,edges,number):
    w=350;h=180;stride=230;width=1120;height=max(1,len(edges))*stride+110
    px=30;cx=740;py=95+(max(1,len(edges))-1)*stride/2
    parts=[f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {width} {height}" role="img" aria-label="{escape(title)}"><rect width="100%" height="100%" fill="#f8fafc"/>']
    canvas=Image.new('RGB',(width,height),'#f8fafc');draw=ImageDraw.Draw(canvas)
    def text(x,y,label,size=16,color='#263b55',bold=False):
        path='C:/Windows/Fonts/arialbd.ttf' if bold else 'C:/Windows/Fonts/arial.ttf'
        font=ImageFont.truetype(path,size)
        parts.append(f'<text x="{x}" y="{y}" font-family="Arial,sans-serif" font-size="{size}" fill="{color}"'+(' font-weight="bold"' if bold else '')+'>'+escape(label)+'</text>')
        draw.text((x,y),label,font=font,fill=color,anchor='ls')
    def rect(x,y,ww,hh,fill,stroke=None,r=0):
        parts.append(f'<rect x="{x}" y="{y}" width="{ww}" height="{hh}" rx="{r}" fill="{fill}"'+(f' stroke="{stroke}"' if stroke else '')+'/>')
        draw.rounded_rectangle((x,y,x+ww,y+hh),radius=r,fill=fill,outline=stroke)
    def line(points,dotted=False):
        path='M '+' L '.join(f'{x} {y}' for x,y in points)
        parts.append(f'<path d="{path}" fill="none" stroke="{('#aa2847' if dotted else '#506b89')}" stroke-width="2.5"'+(' stroke-dasharray="6 5"' if dotted else '')+'/>')
        if not dotted:draw.line(points,fill='#506b89',width=3)
        else:
            for (x1,y1),(x2,y2) in zip(points,points[1:]):
                length=max(abs(x2-x1),abs(y2-y1))
                for offset in range(0,int(length),11):
                    end=min(offset+6,length)
                    if length:draw.line([(x1+(x2-x1)*offset/length,y1+(y2-y1)*offset/length),(x1+(x2-x1)*end/length,y1+(y2-y1)*end/length)],fill='#aa2847',width=3)
    def card(table,x,y,focus=None):
        data=schema[table];fkset={f['COLUMN_NAME'] for f in data['foreign_keys']}
        selected=[c for c in data['columns'] if c['COLUMN_KEY']=='PRI']
        if focus:selected += [c for c in data['columns'] if c['COLUMN_NAME']==focus and c not in selected]
        selected += [c for c in data['columns'] if c['COLUMN_NAME'] in fkset and c not in selected]
        selected=selected[:4]
        color='#64748b' if table in legacy else '#183654'
        rect(x,y,w,h,'white','#ccd8e5',10);rect(x,y,w,43,color,r=10)
        text(x+16,y+28,table,19,'white',True)
        for i,c in enumerate(selected):
            key=('PK' if c['COLUMN_KEY']=='PRI' else 'FK' if c['COLUMN_NAME'] in fkset else 'APP')
            rect(x+15,y+57+i*27,36,21,'#e7edf5',r=4)
            text(x+20,y+72+i*27,key,11,'#365777',True)
            text(x+62,y+73+i*27,c['COLUMN_NAME']+(' ?' if c['IS_NULLABLE']=='YES' else ''),15)
        text(x+16,y+h-12,'Key fields only · full fields in dictionary',12,'#64748b')
    text(30,36,title,24,'#183654',True)
    text(30,64,'Parent entity on the left · related child entities on the right',15,'#64748b')
    for i,(child,col,dotted) in enumerate(edges):
        y=95+i*stride;center=y+h/2
        # Connection enters card border, never passes through a table.
        line([(px+w,py+h/2),(420,py+h/2),(420,center),(cx,center)],dotted)
        c=next(c for c in schema[child]['columns'] if c['COLUMN_NAME']==col)
        parent_count='0..1 parent' if c['IS_NULLABLE']=='YES' else '1 parent'
        child_count='0..1 child' if c['COLUMN_KEY'] in ('PRI','UNI') else '0..many children'
        text(450,center-34,col+(' · PHP only' if dotted else ''),15,'#aa2847' if dotted else '#365777',True)
        text(450,center-10,parent_count+' / '+child_count,14)
        # Arrow shows reading direction; text carries exact ER cardinality.
        line([(cx-12,center-6),(cx,center),(cx-12,center+6)],dotted)
        card(child,cx,y,col)
    card(parent,px,py)
    text(30,height-15,'Solid = enforced FK     Dashed = PHP relationship     ? = nullable     Grey = retained feature',14,'#64748b')
    parts.append('</svg>');svg=''.join(parts)
    (OUT/f'HEMOPULSE_ERD_{number}.svg').write_text(svg,encoding='utf-8')
    canvas.save(OUT/f'HEMOPULSE_ERD_{number}.png')
    return svg

svgpanels=''.join('<section class="focus-panel" id="relation-'+str(i+1)+'"><h3>'+escape(t)+'</h3><div class="diagram">'+diagram(t,parent,edges,i+1)+'</div></section>' for i,(t,parent,edges) in enumerate(groups))
covered={(parent,child,col) for _,parent,edges in groups for child,col,dotted in edges if not dotted}
assert len(covered)==sum(len(d['foreign_keys']) for d in schema.values()), 'Missing physical relationship'
standalone=[t for t,d in schema.items() if not d['foreign_keys'] and t not in relations]
svgpanels+='<section class="note"><strong>Tables without foreign-key relationships:</strong> '+', '.join('<a href="#entity-'+t+'">'+t+'</a>' for t in standalone)+'. Their complete columns are in the dictionary below.</section>'

# Keep the complete model available without making it the first reading view.
focused='## Readable relationship views\n\nEach view shows one parent and up to three child relationships, key fields, and labelled cardinalities. Start with roles and users, then campaigns, eligibility, appointments and donation provenance.\n\n'
for i,(title,parent,edges) in enumerate(groups):
    focused+=f'- [{title}](HEMOPULSE_ERD_{i+1}.svg)\n'
focused+='\nThe printable HTML guide displays all these views and preserves the API map and complete dictionary.\n\n'
md=md.replace('## Complete physical ERD\n\n',focused+'<details>\n<summary>Complete physical ERD — all 24 tables</summary>\n\n')
md=md.replace('\n## API architecture','\n</details>\n\n## API architecture',1)
(OUT/'HEMOPULSE_ERD.md').write_text(md,encoding='utf-8')

def table(headers, rows):
    return '<div class="tablewrap"><table><thead><tr>'+''.join('<th>'+escape(h)+'</th>' for h in headers)+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+escape(str(c))+'</td>' for c in row)+'</tr>' for row in rows)+'</tbody></table></div>'
dictionary=''
for name,data in schema.items():
    fks={f['COLUMN_NAME']:f['REFERENCED_TABLE_NAME']+'.'+f['REFERENCED_COLUMN_NAME'] for f in data['foreign_keys']}
    dictionary+='<details id="entity-'+name+'"><summary>'+escape(name)+(' · retained feature' if name in legacy else '')+'</summary>'+table(['Column','Database type','Key','Nullable','References'],[(c['COLUMN_NAME'],c['COLUMN_TYPE'],c['COLUMN_KEY'],c['IS_NULLABLE'],fks.get(c['COLUMN_NAME'],'PHP reviewer relationship to users.user_id' if name=='eligibility_checks' and c['COLUMN_NAME']=='reviewed_by' else '')) for c in data['columns']])+'</details>'
html='''<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HemoPulse · ERD and APIs</title><style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#20344f;font:16px/1.6 system-ui,sans-serif}header{background:#183654;color:white;padding:38px 5vw}h1{margin:0;font-size:38px}header p{max-width:950px;color:#d6e4f5}main{padding:25px 4vw;max-width:1900px;margin:auto}nav{display:flex;gap:20px;flex-wrap:wrap}a{color:#a62544}section{margin:30px 0}h2{font-size:26px}.diagram{overflow:auto;background:white;border:1px solid #d5dfeb;border-radius:12px}.diagram svg{display:block;min-width:900px;width:100%;height:auto;max-width:1120px;margin:auto}.focus-panel{max-width:1160px;margin:32px auto}.focus-panel h3{font-size:21px;margin:0 0 10px}.diagram{padding:8px}.relation-index{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0}.relation-index a{padding:7px 12px;border:1px solid #d5dfeb;border-radius:6px;background:white;font-size:14px}table{border-collapse:collapse;width:100%;background:white;font-size:14px}th{text-align:left;background:#183654;color:white}td,th{padding:12px;border:1px solid #dce4ee;vertical-align:top}.tablewrap{overflow:auto}details{margin:12px 0;background:white;border:1px solid #dce4ee;border-radius:8px;padding:15px}summary{font-weight:650;cursor:pointer}.note{padding:18px 22px;background:#fff;border-left:4px solid #aa2847}.print{background:#aa2847;color:white;border:0;border-radius:6px;padding:10px 18px;font:inherit;cursor:pointer}code{background:#e7edf5;padding:2px 5px}footer{margin:30px 0;color:#61718b}@media print{nav,.print{display:none}body{background:white}header{background:white;color:#183654;padding:10px}header p{color:#20344f}main{padding:0}.diagram svg{min-width:0}section{break-inside:avoid}table{font-size:10px}td,th{padding:5px}details{break-inside:avoid} .tablewrap{overflow:visible}}@page{size:A3 landscape;margin:12mm}
</style><header><h1>HemoPulse · ERD and API map</h1><p>24 database tables · Current PHP implementation · October 4, 2026<br>Database relationships and endpoint data effects, with retained features clearly identified.</p><button class="print" onclick="document.querySelectorAll('details').forEach(d=>d.open=true);window.print()">Print / save PDF</button></header><main><nav><a href="#erd">ERD diagrams</a><a href="#apis">JSON APIs</a><a href="#handlers">Other endpoints</a><a href="#dictionary">Complete fields</a><a href="HEMOPULSE_ERD.md">Mermaid source and detailed notes</a></nav><section class="note"><strong>How to read:</strong> PK = primary key; FK = database foreign key; UK = individually unique key; ? = nullable. Read each diagram left to right. Every connector names the linking column and gives parent / child cardinality. Dotted reviewer relationship is enforced by PHP, not a database FK. Grey headings are retained features. Repeated tables connect the separate panels. Each diagram shows up to three relationships and four key fields per table, with no unrelated crossing lines. The dictionary includes every field.</section><div id="erd"><h2>Focused entity relationships</h2><p>Start with Accounts and roles, then follow campaigns, screening, appointments and donation provenance. The same entity appears in multiple focused views so you can read each relationship clearly.</p><div class="relation-index">'''+''.join('<a href="#relation-'+str(i+1)+'">'+escape(t)+'</a>' for i,(t,_,_) in enumerate(groups))+'</div>'+svgpanels+'''</div><section class="note"><strong>Program boundaries:</strong> APIs are operations, not entities. Blood inventory links to donations through inventory_transactions. Public emails do not link to users by FK. OTPs and CSRF tokens live in PHP sessions. Profiles and mail previews are files. SMTP is an external transport. The recipient fulfillment handler is disabled (410); no active request/threshold mutation API exists. Current throttling uses request_limits, not login_attempts.</section><section id="apis"><h2>Main JSON APIs</h2><p>Base: <code>/hemopulse/api/index.php/</code>. Collection POST; item PUT/DELETE where shown. Session cookies authenticate; mutations require <code>X-CSRF-Token</code>. Public reads: session, campaigns, categories. GET inventory and staff reports may write expiry updates. See the notes in the Markdown guide for behavior and access details.</p>'''+table(['Method','Route','Access','Reads','Writes / side effects'],apis)+'''</section><section id="handlers"><h2>Form handlers and compatibility endpoints</h2>'''+table(['Endpoint','Access / purpose','Data or service'],handlers)+'''</section><section id="dictionary"><h2>Complete data dictionary</h2><p>Actual installed columns and SQL types. PRI = primary; UNI = individually unique; MUL = indexed, including foreign keys or compound indexes.</p>'''+dictionary+'''</section><footer>Verified against schema metadata and source code. No personal records were exported. This document does not assert that every endpoint was runtime tested.</footer></main></html>'''
(OUT/'HEMOPULSE_ERD.html').write_text(html,encoding='utf-8')
print(f'Generated ERD for {len(schema)} tables, {sum(len(d["foreign_keys"]) for d in schema.values())} enforced foreign keys, {len(apis)} API operations/groups.')
