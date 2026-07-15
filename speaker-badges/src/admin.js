// The admin console (Cloudflare Access-protected). All fetches are same-origin so
// the Access session cookie rides along — no Authorization header, no admin key.

import { layout } from "./render.js";

export function adminPage() {
  const body = `
<section class="section tartan on-dark" style="color:#fff">
  <div class="inner">
    <h1>Badges admin console</h1>
    <p>Issue and manage verified Scottish Summit badges. You're signed in via Cloudflare Access.</p>
  </div>
</section>
<div class="wrap">

  <div class="card plain" id="eventCard">
    <h2>Event &amp; artwork</h2>
    <div class="row" style="display:flex;gap:16px;flex-wrap:wrap">
      <div style="flex:1;min-width:220px">
        <label for="ev-name">Event name</label>
        <input id="ev-name" placeholder="Scottish Summit">
      </div>
      <div style="width:120px">
        <label for="ev-year">Year</label>
        <input id="ev-year" inputmode="numeric" placeholder="2026">
      </div>
      <div style="width:110px">
        <label for="ev-month">Month</label>
        <input id="ev-month" inputmode="numeric" placeholder="6">
      </div>
    </div>
    <div class="row" style="display:flex;gap:16px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px"><label for="ev-orgid">LinkedIn organisation ID</label><input id="ev-orgid" placeholder="12345678"></div>
      <div style="flex:1;min-width:200px"><label for="ev-orgname">Organisation name</label><input id="ev-orgname" placeholder="Scottish Summit"></div>
    </div>
    <div class="btnbar" style="margin-top:14px"><button class="btn" id="ev-save">Save event</button> <span id="ev-status" class="status" role="status" aria-live="polite"></span></div>

    <h3 style="margin-top:22px">Badge artwork (one image per role, ≤ 2 MB)</h3>
    <div id="artSlots" style="display:flex;gap:16px;flex-wrap:wrap">
      ${["speaker", "volunteer", "organiser", "milestone5"].map(artSlot).join("")}
    </div>
  </div>

  <div class="card plain">
    <h2>Issue badges</h2>
    <div role="tablist" aria-label="Issue mode" class="btnbar" style="margin-bottom:12px">
      <button class="btn inverse" id="tab-roster" role="tab" aria-selected="true">Single event roster</button>
      <button class="btn inverse" id="tab-bulk" role="tab" aria-selected="false">Bulk import (all years)</button>
    </div>

    <div id="pane-roster">
      <p>CSV columns: <code>name,role,email</code> (optional <code>month,orgId,orgName</code>). Uses the event above.</p>
      <textarea id="roster-csv" rows="7" placeholder="name,role,email&#10;Ada Lovelace,speaker,ada@example.com"></textarea>
      <label class="inline" style="font-weight:600;margin-top:10px"><input type="checkbox" id="roster-email" checked style="width:auto"> Send badge emails</label>
      <div class="btnbar"><button class="btn" id="roster-go">Issue roster</button></div>
    </div>

    <div id="pane-bulk" hidden>
      <p>ONE combined CSV, columns: <code>event,year,name,role,email</code> (optional <code>month,orgId,orgName</code>).</p>
      <textarea id="bulk-csv" rows="8" placeholder="event,year,name,role,email&#10;Scottish Summit,2020,Ada Lovelace,speaker,ada@example.com"></textarea>
      <label class="inline" style="font-weight:600;margin-top:10px"><input type="checkbox" id="bulk-email" checked style="width:auto"> Send badge emails</label>
      <div class="btnbar"><button class="btn" id="bulk-go">Import all years</button> <button class="btn inverse" id="milestone-go">Recompute milestones</button></div>
    </div>

    <p id="issue-status" class="status" role="status" aria-live="polite"></p>
    <div id="issue-results"></div>
  </div>

  <div class="card plain">
    <h2>Unsent emails</h2>
    <p>Send the badge email to anyone issued but not yet emailed.</p>
    <div class="btnbar"><button class="btn inverse" id="unsent-go">Email unsent badges</button> <span id="unsent-status" class="status" role="status" aria-live="polite"></span></div>
  </div>

  <div class="card plain">
    <h2>Requests</h2>
    <div class="btnbar"><button class="btn inverse" id="req-refresh">Refresh</button> <span id="req-status" class="status" role="status" aria-live="polite"></span></div>
    <div id="req-list" style="margin-top:12px"></div>
  </div>

</div>
${adminScript()}`;

  return layout({ title: "Admin — Scottish Summit Badges", current: "badges", body, multiSection: true });
}

function artSlot(role) {
  const labels = { speaker: "Speaker", volunteer: "Volunteer", organiser: "Organiser", milestone5: "5-Year" };
  return `<div class="card" style="width:180px;text-align:center;padding:12px">
    <strong>${labels[role]}</strong>
    <div id="art-thumb-${role}" style="height:110px;display:flex;align-items:center;justify-content:center;color:#888;font-size:12px">No image</div>
    <input type="file" id="art-file-${role}" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="font-size:12px">
    <button class="btn inverse" data-role="${role}" style="margin-top:8px;padding:7px 12px;font-size:13px">Upload</button>
  </div>`;
}

function adminScript() {
  // Kept as a template string; runs in the browser. Same-origin fetch (Access cookie).
  return `<script>
function eventId(){var n=(document.getElementById('ev-name').value||'').trim().toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');return n+'-'+(document.getElementById('ev-year').value||'').trim();}
function evFields(){return {name:document.getElementById('ev-name').value,year:document.getElementById('ev-year').value,month:document.getElementById('ev-month').value,orgId:document.getElementById('ev-orgid').value,orgName:document.getElementById('ev-orgname').value};}
async function api(path,opts){var r=await fetch(path,opts);var j=await r.json().catch(function(){return{}});if(!r.ok)throw new Error(j.error||('HTTP '+r.status));return j;}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

document.getElementById('ev-save').addEventListener('click',async function(){
  var s=document.getElementById('ev-status');s.className='status';s.textContent='Saving…';
  try{var j=await api('/api/event',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(evFields())});s.className='status ok';s.textContent='Saved '+j.eventId;}
  catch(e){s.className='status err';s.textContent=e.message;}
});

document.querySelectorAll('#artSlots button[data-role]').forEach(function(btn){
  btn.addEventListener('click',async function(){
    var role=btn.getAttribute('data-role');var file=document.getElementById('art-file-'+role).files[0];
    var s=document.getElementById('ev-status');
    if(!file){s.className='status err';s.textContent='Choose a file first';return;}
    if(!eventId()||eventId().charAt(0)==='-'){s.className='status err';s.textContent='Set event name + year first';return;}
    s.className='status';s.textContent='Uploading '+role+'…';
    try{
      var r=await fetch('/api/upload?kind=art&eventId='+encodeURIComponent(eventId())+'&role='+role,{method:'POST',headers:{'content-type':file.type},body:file});
      var j=await r.json();if(!r.ok)throw new Error(j.error||'upload failed');
      document.getElementById('art-thumb-'+role).innerHTML='<img src="/img/'+esc(j.key)+'?t='+Date.now()+'" alt="" style="max-height:100px">';
      s.className='status ok';s.textContent=role+' uploaded';
    }catch(e){s.className='status err';s.textContent=e.message;}
  });
});

// tabs
function showPane(which){
  document.getElementById('pane-roster').hidden=which!=='roster';
  document.getElementById('pane-bulk').hidden=which!=='bulk';
  document.getElementById('tab-roster').setAttribute('aria-selected',which==='roster');
  document.getElementById('tab-bulk').setAttribute('aria-selected',which==='bulk');
}
document.getElementById('tab-roster').addEventListener('click',function(){showPane('roster');});
document.getElementById('tab-bulk').addEventListener('click',function(){showPane('bulk');});

function renderIssued(j){
  var el=document.getElementById('issue-results');var html='';
  if(j.rejected&&j.rejected.length){html+='<div class="card" style="border-color:#8a0d3f;margin-top:12px"><strong>Rejected rows ('+j.rejected.length+')</strong><ul>'+j.rejected.map(function(r){return '<li>'+esc(r.reason)+': '+esc(JSON.stringify(r.row))+'</li>';}).join('')+'</ul></div>';}
  var groups=j.groups||[{key:'',issued:j.issued||[]}];
  html+=groups.map(function(g){return '<div class="card plain" style="margin-top:12px"><strong>'+esc(g.key||'Issued')+' — '+(g.issued?g.issued.length:0)+'</strong><div style="max-height:220px;overflow:auto"><table style="width:100%;font-size:13px"><tr><th style="text-align:left">Name</th><th style="text-align:left">Role</th><th style="text-align:left">Badge</th></tr>'+(g.issued||[]).map(function(a){return '<tr><td>'+esc(a.name)+'</td><td>'+esc(a.role)+'</td><td><a href="'+esc(a.badgeUrl)+'">link</a></td></tr>';}).join('')+'</table></div></div>';}).join('');
  if(j.milestones&&j.milestones.length){html+='<div class="card" style="margin-top:12px"><strong>New 5-Year milestones: '+j.milestones.length+'</strong></div>';}
  el.innerHTML=html;
}

document.getElementById('roster-go').addEventListener('click',async function(){
  var s=document.getElementById('issue-status');s.className='status';s.textContent='Issuing…';
  try{var j=await api('/api/roster',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(Object.assign(evFields(),{csv:document.getElementById('roster-csv').value,sendEmail:document.getElementById('roster-email').checked}))});
    s.className='status ok';s.textContent='Issued '+(j.issued?j.issued.length:0)+' badges.';renderIssued(j);}
  catch(e){s.className='status err';s.textContent=e.message;}
});

document.getElementById('bulk-go').addEventListener('click',async function(){
  var s=document.getElementById('issue-status');
  var send=document.getElementById('bulk-email').checked;
  // Confirm large sends.
  var lines=(document.getElementById('bulk-csv').value.trim().split(/\\n/).length-1);
  if(send&&lines>20&&!confirm('This will email about '+lines+' people — continue?')){return;}
  s.className='status';s.textContent='Importing…';
  try{var j=await api('/api/import',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify(Object.assign(evFields(),{csv:document.getElementById('bulk-csv').value,sendEmail:send}))});
    s.className='status ok';s.textContent='Imported '+(j.total||0)+' badges across '+(j.groups?j.groups.length:0)+' events.';renderIssued(j);}
  catch(e){s.className='status err';s.textContent=e.message;}
});

document.getElementById('milestone-go').addEventListener('click',async function(){
  var s=document.getElementById('issue-status');s.className='status';s.textContent='Recomputing…';
  try{var j=await api('/api/milestones/recompute',{method:'POST'});s.className='status ok';s.textContent='Issued '+(j.milestones?j.milestones.length:0)+' new 5-Year badges.';renderIssued({milestones:j.milestones});}
  catch(e){s.className='status err';s.textContent=e.message;}
});

document.getElementById('unsent-go').addEventListener('click',async function(){
  var s=document.getElementById('unsent-status');s.className='status';s.textContent='Sending…';
  try{var j=await api('/api/email/unsent',{method:'POST'});s.className='status ok';s.textContent='Emailed '+(j.sent||0)+' badges.';}
  catch(e){s.className='status err';s.textContent=e.message;}
});

async function loadRequests(){
  var s=document.getElementById('req-status');s.className='status';s.textContent='Loading…';
  try{var j=await api('/api/requests');var el=document.getElementById('req-list');
    var pend=(j.requests||[]).filter(function(r){return r.status==='pending';});
    s.className='status';s.textContent=pend.length+' pending';
    el.innerHTML=pend.map(function(r){return '<div class="card" style="margin-bottom:10px"><strong>'+esc(r.name)+'</strong> — '+esc(r.role)+' — '+esc(r.eventName)+'<br><small>'+esc(r.email)+(r.message?' · '+esc(r.message):'')+'</small><div class="btnbar"><button class="btn" data-approve="'+esc(r.id)+'">Approve</button> <button class="btn inverse" data-reject="'+esc(r.id)+'">Reject</button></div></div>';}).join('')||'<p>No pending requests.</p>';
    el.querySelectorAll('[data-approve]').forEach(function(b){b.addEventListener('click',function(){decide('approve',b.getAttribute('data-approve'));});});
    el.querySelectorAll('[data-reject]').forEach(function(b){b.addEventListener('click',function(){decide('reject',b.getAttribute('data-reject'));});});
  }catch(e){s.className='status err';s.textContent=e.message;}
}
async function decide(kind,id){try{await api('/api/requests/'+kind,{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({id:id})});loadRequests();}catch(e){document.getElementById('req-status').textContent=e.message;}}
document.getElementById('req-refresh').addEventListener('click',loadRequests);
loadRequests();
</script>`;
}
