const API='../api/';
let allData=[];
let categories=[], locations=[], departments=[];

const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
async function api(url, options={}) {
  const r=await fetch(url, options);
  const d=await r.json().catch(()=>({success:false,message:'Invalid server response'}));
  if(!r.ok || !d.success) {
    if(r.status===401) location.href='../login.html';
    throw new Error(d.message || 'Request failed');
  }
  return d;
}
async function init(){
  try {
    const auth=await api(API+'auth.php');
    if(!auth.authenticated) return location.href='../login.html';
    $('adminName').textContent=auth.admin.full_name;
    const [c,l,d]=await Promise.all([api(API+'categories.php'),api(API+'locations.php'),api(API+'departments.php')]);
    categories=c.data;locations=l.data;departments=d.data;
    $('categoryFilter').innerHTML='<option value="">All Categories</option>'+categories.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join('');
    $('locationFilter').innerHTML='<option value="">All Locations</option>'+locations.map(x=>`<option value="${x.id}">${esc(x.name)}</option>`).join('');
    renderDepartments();
    ['statusFilter','categoryFilter','locationFilter','priorityFilter','dateFilter'].forEach(id=>$(id).addEventListener('change',loadDashboard));
    await loadDashboard();
  } catch(e) { alert(e.message); }
}
async function loadDashboard(){
  const params=new URLSearchParams();
  const map={status:'statusFilter',category:'categoryFilter',location:'locationFilter',priority:'priorityFilter',date:'dateFilter'};
  Object.entries(map).forEach(([p,id])=>{if($(id).value)params.set(p,$(id).value)});
  const d=await api(API+'reports.php?'+params.toString());
  allData=d.data;
  $('total').textContent=d.stats.total||0;$('pending').textContent=d.stats.pending||0;$('progress').textContent=d.stats.in_progress||0;$('resolved').textContent=d.stats.resolved||0;
  renderTable(allData);renderBars('categoryChart',d.analytics.category);renderBars('locationChart',d.analytics.location);renderMonthly(d.analytics.monthly);
  const total=Number(d.stats.total)||1, resolved=Number(d.stats.resolved)||0;
  const deg=Math.round(resolved/total*360);$('statusChart').style.background=`conic-gradient(#3a9758 0 ${deg}deg,#f0b24c ${deg}deg 360deg)`;
}
function renderTable(rows){
  $('reportsBody').innerHTML=rows.length?rows.map(r=>`<tr>
  <td><strong>${esc(r.report_code)}</strong></td>
  <td>${r.image_path?`<img class="thumb" src="../${esc(r.image_path)}" alt="Issue photo">`:'<div class="thumb">—</div>'}</td>
  <td>${esc(r.category)}</td><td>${esc(r.location)}</td><td>${esc(r.reporter)}</td>
  <td>${new Date(r.created_at.replace(' ','T')).toLocaleDateString()}</td>
  <td class="priority-${esc(r.priority)}">${esc(r.priority)}</td>
  <td><span class="badge ${esc(r.status).replace(' ','-')}">${esc(r.status)}</span></td>
  <td><a class="action-link" href="report.html?id=${r.id}">View →</a></td></tr>`).join(''):'<tr><td colspan="9" style="text-align:center;padding:35px;color:#819087">No reports match these filters.</td></tr>';
}
function renderBars(id,items){
  const max=Math.max(...items.map(x=>Number(x.value)),1);
  $(id).innerHTML=items.slice(0,8).map(x=>`<div class="bar-row"><span title="${esc(x.label)}">${esc(x.label).slice(0,22)}</span><div class="bar-track"><div class="bar-fill" style="width:${Number(x.value)/max*100}%"></div></div><b>${x.value}</b></div>`).join('')||'<div style="color:#819087">No data</div>';
}
function renderMonthly(items){
  const max=Math.max(...items.map(x=>Number(x.value)),1);
  $('monthlyChart').innerHTML=items.map(x=>`<div class="bar-row"><span>${esc(x.label)}</span><div class="bar-track"><div class="bar-fill" style="--h:${Number(x.value)/max*100}%"></div></div><b>${x.value}</b></div>`).join('')||'<div style="color:#819087">No data</div>';
}
function renderDepartments(){
 $('departmentsList').innerHTML=departments.map(d=>`<div class="dept-item"><strong>${esc(d.name)}</strong><small>${esc(d.contact_placeholder)}</small></div>`).join('');
}
function clearFilters(){['statusFilter','categoryFilter','locationFilter','priorityFilter','dateFilter'].forEach(id=>$(id).value='');loadDashboard();}
$('logoutBtn').onclick=async()=>{await api(API+'logout.php',{method:'POST'});location.href='../login.html';};
init();
