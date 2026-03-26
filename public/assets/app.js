const state={payload:null,query:"",family:"all",status:"all",node:"all",sort:"weighted_score",page:1,pageSize:25,expanded:new Set(),trendMetric:"cpu_percent",trendWindow:"60"};
const pageName=document.body.dataset.page||"servers";
const summaryGrid=document.getElementById("summary-grid");
const statusGrid=document.getElementById("status-grid");
const insightGrid=document.getElementById("insight-grid");
const nodeList=document.getElementById("node-list");
const nodeServerGroups=document.getElementById("node-server-groups");
const nodeCapacityGrid=document.getElementById("node-capacity-grid");
const trendList=document.getElementById("trend-list");
const trendTitle=document.getElementById("trend-title");
const serverTable=document.getElementById("server-table");
const tablePagination=document.getElementById("table-pagination");
const statusEl=document.getElementById("connection-status");
const refreshValueEl=document.getElementById("refresh-value");
const modeValueEl=document.getElementById("mode-value");
const searchInput=document.getElementById("search-input");
const refreshButton=document.getElementById("manual-refresh");
const chartCanvas=document.getElementById("trend-chart");
const chartContext=chartCanvas?chartCanvas.getContext("2d"):null;
const errorBanner=document.getElementById("error-banner");
const menuToggle=document.getElementById("menu-toggle");
const sideMenu=document.getElementById("side-menu");
const menuOverlay=document.getElementById("menu-overlay");
const statusFilter=document.getElementById("status-filter");
const familyFilter=document.getElementById("family-filter");
const nodeFilter=document.getElementById("node-filter");
const sortSelect=document.getElementById("sort-select");
const trendMetricTabs=document.getElementById("trend-metric-tabs");
const trendWindowTabs=document.getElementById("trend-window-tabs");

async function loadOverview(){
 if(statusEl){statusEl.textContent="Veri aliniyor";}
 hideError();
 try{
  const response=await fetch("/api/overview.php",{cache:"no-store"});
  const payload=await response.json();
  if(!payload.ok){throw new Error(payload.message||"API hatasi");}
  state.payload=payload;
  if(refreshValueEl){refreshValueEl.textContent=`${payload.app.refresh_seconds} sn`;}
  if(modeValueEl){modeValueEl.textContent=payload.app.demo_mode?"Demo":"Canli API";}
  if(statusEl){statusEl.textContent=`Bagli | ${new Date(payload.data.generated_at).toLocaleTimeString("tr-TR")}`;}
  renderDashboard();
  scheduleNext(payload.app.refresh_seconds);
 }catch(error){
  if(statusEl){statusEl.textContent=`Hata | ${error.message}`;}
  showError(`Pterodactyl paneline baglanilamadi: ${error.message}`);
  scheduleNext(20);
 }
}

let refreshTimer=null;
function scheduleNext(seconds){
 if(refreshTimer){clearTimeout(refreshTimer);}
 refreshTimer=setTimeout(loadOverview,seconds*1000);
}

function renderDashboard(){
 const{summary,nodes,servers}=state.payload.data;
 renderSummary(summary,servers,nodes);
 if(pageName==="servers"){
  renderFilterControls(servers);
  renderTrendControls();
  const filteredServers=getFilteredServers(servers);
  renderStatusBoard(filteredServers);
  renderInsights(filteredServers);
  renderTrend(filteredServers);
  renderTable(filteredServers);
 }
 if(pageName==="nodes"){
  renderNodes(nodes);
  renderNodeCapacity(nodes);
  renderNodeGroups(servers);
 }
}

function getFilteredServers(servers){
 const query=state.query.trim().toLowerCase();
 const filtered=servers.filter((server)=>{
  const familyPass=state.family==="all"||server.type.family===state.family;
  const statusPass=state.status==="all"||getStatusMeta(server).label===state.status;
  const nodePass=state.node==="all"||server.node===state.node;
  const haystack=[server.name,server.identifier,server.uuid,server.node,server.owner,server.status,server.type.label,server.type.family,server.type.nest,server.type.egg].join(" ").toLowerCase();
  return familyPass&&statusPass&&nodePass&&haystack.includes(query);
 });
 return filtered.sort((left,right)=>compareServers(left,right,state.sort));
}

function compareServers(left,right,sortKey){
 switch(sortKey){
  case"cpu":return right.metrics.cpu_percent-left.metrics.cpu_percent;
  case"memory":return right.metrics.memory_percent-left.metrics.memory_percent;
  case"disk":return right.metrics.disk_percent-left.metrics.disk_percent;
  case"network":return getNetworkTotal(right)-getNetworkTotal(left);
  case"uptime":return right.metrics.uptime-left.metrics.uptime;
  case"name":return left.name.localeCompare(right.name,"tr");
  case"weighted_score":
  default:return right.weighted_score-left.weighted_score;
 }
}

function renderSummary(summary,servers,nodes){
 if(!summaryGrid){return;}
 if(pageName==="nodes"){
  const totalNodeMemory=nodes.reduce((total,node)=>total+((node.capacity.memory_mb||0)*1024*1024),0);
  const totalNodeDisk=nodes.reduce((total,node)=>total+((node.capacity.disk_mb||0)*1024*1024),0);
  const totalCpuLimit=nodes.reduce((total,node)=>total+(node.capacity.cpu_limit||0),0);
  const nodeCards=[
   {label:"Toplam Node",value:nodes.length,tone:"neutral"},
   {label:"Toplam Sunucu",value:summary.server_count,tone:"neutral"},
   {label:"Toplam RAM",value:formatBytes(totalNodeMemory),tone:"accent"},
   {label:"Toplam Depolama",value:formatBytes(totalNodeDisk),tone:"neutral"},
   {label:"Toplam Cekirdek",value:formatCpuLimit(totalCpuLimit),tone:"neutral"},
   {label:"Toplam CPU Yuk",value:`${summary.cpu_percent_total.toFixed(1)}%`,tone:"accent"},
   {label:"Anlik RAM",value:formatBytes(summary.memory_bytes_total),tone:"neutral"},
   {label:"Anlik Disk",value:formatBytes(summary.disk_bytes_total),tone:"neutral"},
  ];
  summaryGrid.innerHTML=nodeCards.map(renderStatCard).join("");
  return;
 }
 const avgCpu=servers.length?summary.cpu_percent_total/servers.length:0;
 const avgMemory=servers.length?servers.reduce((total,server)=>total+server.metrics.memory_percent,0)/servers.length:0;
 const criticalCount=servers.filter((server)=>server.severity==="critical").length;
 const typeCount=new Set(servers.map((server)=>server.type.label)).size;
 const cards=[
  {label:"Toplam Sunucu",value:summary.server_count,tone:"neutral"},
  {label:"Toplam CPU Yuk",value:`${summary.cpu_percent_total.toFixed(1)}%`,tone:"accent"},
  {label:"Ortalama CPU",value:`${avgCpu.toFixed(1)}%`,tone:"neutral"},
  {label:"Ortalama RAM",value:`${avgMemory.toFixed(1)}%`,tone:"neutral"},
  {label:"Toplam Ag",value:formatBytes(summary.network_total_bytes),tone:"neutral"},
  {label:"Uyari Veren",value:summary.warning_count,tone:"warning"},
  {label:"Kritik Yuk",value:criticalCount,tone:"critical"},
  {label:"Sunucu Turu",value:typeCount,tone:"neutral"},
 ];
 summaryGrid.innerHTML=cards.map(renderStatCard).join("");
}

function renderStatCard(card){
 return `<article class="stat-card ${card.tone}"><span>${escapeHtml(card.label)}</span><strong>${escapeHtml(String(card.value))}</strong></article>`;
}

function renderStatusBoard(servers){
 if(!statusGrid){return;}
 const cards=[
  {label:"Toplam",value:servers.length,tone:"neutral",note:"Filtre sonrasi gorunum"},
  {label:"Acik",value:servers.filter((server)=>getStatusMeta(server).label==="Acik").length,tone:"accent",note:"Canli sinyal alinanlar"},
  {label:"Kapali",value:servers.filter((server)=>getStatusMeta(server).label==="Kapali").length,tone:"critical",note:"Calismayanlar"},
  {label:"Kuruluyor",value:servers.filter((server)=>getStatusMeta(server).label==="Kuruluyor").length,tone:"warning",note:"Gecis durumlari"},
  {label:"Uyari",value:servers.filter((server)=>server.severity!=="healthy").length,tone:"warning",note:"Yuksek tuketim grubu"},
 ];
 statusGrid.innerHTML=cards.map((card)=>`<article class="status-card ${card.tone}"><span>${escapeHtml(card.label)}</span><strong>${escapeHtml(String(card.value))}</strong><small>${escapeHtml(card.note)}</small></article>`).join("");
}

function renderInsights(servers){
 if(!insightGrid){return;}
 if(!servers.length){
  insightGrid.innerHTML=`<article class="insight-card"><span>Ozet</span><strong>Sunucu bulunamadi</strong><small>Filtreleri temizleyip tekrar deneyebilirsin.</small></article>`;
  return;
 }
 const highestCpu=[...servers].sort((a,b)=>b.metrics.cpu_percent-a.metrics.cpu_percent)[0];
 const highestMemory=[...servers].sort((a,b)=>b.metrics.memory_percent-a.metrics.memory_percent)[0];
 const highestNetwork=[...servers].sort((a,b)=>getNetworkTotal(b)-getNetworkTotal(a))[0];
 const longestUptime=[...servers].sort((a,b)=>b.metrics.uptime-a.metrics.uptime)[0];
 const cards=[
  {label:"En yuksek CPU",title:highestCpu.name,metric:`${highestCpu.metrics.cpu_percent.toFixed(1)}% CPU`,note:`${highestCpu.node} uzerinde`},
  {label:"En yuksek RAM",title:highestMemory.name,metric:`${highestMemory.metrics.memory_percent.toFixed(1)}% RAM`,note:`${formatBytes(highestMemory.metrics.memory_bytes)} kullaniliyor`},
  {label:"Ag lideri",title:highestNetwork.name,metric:formatBytes(getNetworkTotal(highestNetwork)),note:`${highestNetwork.type.label} trafigi`},
  {label:"En uzun uptime",title:longestUptime.name,metric:formatUptime(longestUptime.metrics.uptime),note:`${getStatusMeta(longestUptime).label} durumda`},
 ];
 insightGrid.innerHTML=cards.map((card)=>`<article class="insight-card"><span>${escapeHtml(card.label)}</span><strong>${escapeHtml(card.title)}</strong><p>${escapeHtml(card.metric)}</p><small>${escapeHtml(card.note)}</small></article>`).join("");
}

function renderFilterControls(servers){
 populateSelect(statusFilter,[{value:"all",label:"Tum durumlar"},{value:"Acik",label:"Acik"},{value:"Kapali",label:"Kapali"},{value:"Kuruluyor",label:"Kuruluyor"}],state.status);
 populateSelect(familyFilter,[{value:"all",label:"Tum turler"},...[...new Set(servers.map((server)=>server.type.family).filter(Boolean))].sort((a,b)=>a.localeCompare(b,"tr")).map((family)=>({value:family,label:family}))],state.family);
 populateSelect(nodeFilter,[{value:"all",label:"Tum node'lar"},...[...new Set(servers.map((server)=>server.node).filter(Boolean))].sort((a,b)=>a.localeCompare(b,"tr")).map((node)=>({value:node,label:node}))],state.node);
 populateSelect(sortSelect,[{value:"weighted_score",label:"Skora gore"},{value:"cpu",label:"En yuksek CPU"},{value:"memory",label:"En yuksek RAM"},{value:"disk",label:"En yuksek disk"},{value:"network",label:"En yuksek ag"},{value:"uptime",label:"En uzun uptime"},{value:"name",label:"Ada gore"}],state.sort);
}

function populateSelect(selectEl,items,selectedValue){
 if(!selectEl){return;}
 const markup=items.map((item)=>`<option value="${escapeHtml(item.value)}" ${item.value===selectedValue?"selected":""}>${escapeHtml(item.label)}</option>`).join("");
 if(selectEl.innerHTML!==markup){selectEl.innerHTML=markup;}
}

function renderTrendControls(){
 renderSegmentedControl(trendMetricTabs,[{value:"cpu_percent",label:"CPU"},{value:"memory_percent",label:"RAM"},{value:"disk_percent",label:"Disk"}],state.trendMetric,"data-trend-metric");
 renderSegmentedControl(trendWindowTabs,[{value:"15",label:"Son 15"},{value:"60",label:"Son 60"},{value:"all",label:"Tum kayit"}],state.trendWindow,"data-trend-window");
}

function renderSegmentedControl(host,items,activeValue,attrName){
 if(!host){return;}
 host.innerHTML=items.map((item)=>`<button class="segment-button ${item.value===activeValue?"active":""}" ${attrName}="${escapeHtml(item.value)}">${escapeHtml(item.label)}</button>`).join("");
}

function renderNodes(nodes){
 if(!nodeList){return;}
 nodeList.innerHTML=nodes.map((node)=>`
  <div class="node-item">
    <div class="node-item-main">
      <strong>${escapeHtml(node.name)}</strong>
      <p>${node.server_count} sunucu</p>
      <div class="mini-stack">
        <div class="mini-progress"><label>CPU</label><div class="mini-bar"><span style="width:${Math.min(node.usage.cpu_percent,100)}%"></span></div></div>
        <div class="mini-progress"><label>RAM</label><div class="mini-bar"><span style="width:${Math.min(node.usage.memory_percent,100)}%"></span></div></div>
        <div class="mini-progress"><label>Disk</label><div class="mini-bar"><span style="width:${Math.min(node.usage.disk_percent,100)}%"></span></div></div>
      </div>
    </div>
    <div class="node-metrics">
      <span>CPU ${node.usage.cpu_percent.toFixed(2)}%</span>
      <span>RAM ${node.usage.memory_percent.toFixed(1)}%</span>
      <span>Disk ${node.usage.disk_percent.toFixed(1)}%</span>
    </div>
  </div>
 `).join("");
}

function renderNodeCapacity(nodes){
 if(!nodeCapacityGrid){return;}
 nodeCapacityGrid.innerHTML=nodes.map((node)=>`
  <article class="spotlight-card">
    <div class="spotlight-header">
      <div><p class="spotlight-kicker">Node</p><h3>${escapeHtml(node.name)}</h3></div>
      <span class="type-pill">${escapeHtml(node.fqdn||"Yerel")}</span>
    </div>
    <div class="spotlight-metrics">
      <div><span>Toplam RAM</span><strong>${formatMegabytes(node.capacity.memory_mb)}</strong></div>
      <div><span>Depolama</span><strong>${formatMegabytes(node.capacity.disk_mb)}</strong></div>
      <div><span>Cekirdek</span><strong>${formatCpuLimit(node.capacity.cpu_limit)}</strong></div>
    </div>
    <div class="spotlight-metrics">
      <div><span>Sunucu Sayisi</span><strong>${node.server_count}</strong></div>
      <div><span>CPU Kullanim</span><strong>${node.usage.cpu_percent.toFixed(2)}%</strong></div>
      <div><span>RAM Kullanim</span><strong>${node.usage.memory_percent.toFixed(1)}%</strong></div>
      <div><span>Disk Kullanim</span><strong>${node.usage.disk_percent.toFixed(1)}%</strong></div>
    </div>
  </article>
 `).join("");
}

function renderNodeGroups(servers){
 if(!nodeServerGroups){return;}
 const grouped=new Map();
 servers.forEach((server)=>{
  if(!grouped.has(server.node)){grouped.set(server.node,[]);}
  grouped.get(server.node).push(server);
 });
 nodeServerGroups.innerHTML=[...grouped.entries()].map(([nodeName,nodeServers])=>`
  <section class="node-group">
    <div class="node-group-head"><strong>${escapeHtml(nodeName)}</strong><small>${nodeServers.length} sunucu</small></div>
    <div class="node-group-list">
      ${nodeServers.map((server)=>`<div class="node-group-item"><div><strong>${escapeHtml(server.name)}</strong><small>${escapeHtml(server.type.label)} | ${escapeHtml(getStatusMeta(server).label)}</small></div><span class="node-status ${getStatusMeta(server).tone}">${server.metrics.cpu_percent.toFixed(1)}% CPU</span></div>`).join("")}
    </div>
  </section>
 `).join("");
}

function renderTable(servers){
 if(!serverTable){return;}
 const totalPages=Math.max(Math.ceil(servers.length/state.pageSize),1);
 if(state.page>totalPages){state.page=totalPages;}
 if(!servers.length){
  serverTable.innerHTML=`<tr><td colspan="11"><div class="table-empty"><strong>Listeye girecek sunucu bulunamadi</strong><span>Arama, durum veya node filtresini degistirip tekrar deneyebilirsin.</span></div></td></tr>`;
  renderPagination(0,1);
  return;
 }
 const start=(state.page-1)*state.pageSize;
 const paged=servers.slice(start,start+state.pageSize);
 serverTable.innerHTML=paged.map((server)=>{
  const statusMeta=getStatusMeta(server);
  const expanded=state.expanded.has(server.identifier);
  const networkTotal=getNetworkTotal(server);
  return `
   <tr class="server-row severity-${escapeHtml(server.severity)}">
     <td><div class="server-cell"><strong>${escapeHtml(server.name)}</strong><span>${escapeHtml(server.identifier)}</span></div></td>
     <td><span class="badge ${statusMeta.tone}">${escapeHtml(statusMeta.label)}</span></td>
     <td><div class="type-cell"><span class="type-pill">${escapeHtml(server.type.label)}</span><small>${escapeHtml(server.type.family)}</small></div></td>
     <td>${server.metrics.cpu_percent.toFixed(1)}%</td>
     <td>${formatBytes(server.metrics.memory_bytes)} <small>${server.metrics.memory_percent.toFixed(1)}%</small></td>
     <td>${formatBytes(server.metrics.disk_bytes)} <small>${server.metrics.disk_percent.toFixed(1)}%</small></td>
     <td>${formatBytes(networkTotal)}</td>
     <td>${formatUptime(server.metrics.uptime)}</td>
     <td>${escapeHtml(server.node)}</td>
     <td>${server.weighted_score.toFixed(1)}</td>
     <td><button class="detail-toggle" data-toggle-server="${escapeHtml(server.identifier)}">${expanded?"Gizle":"Detay"}</button></td>
   </tr>
   <tr class="detail-row ${expanded?"":"hidden"}">
     <td colspan="11">
       <div class="detail-panel">
         <div class="detail-grid">
           <div class="detail-card"><span>Kimlik</span><strong>${escapeHtml(server.identifier)}</strong><small>UUID: ${escapeHtml(server.uuid||"-")}</small></div>
           <div class="detail-card"><span>Sunucu Turu</span><strong>${escapeHtml(server.type.label)}</strong><small>${escapeHtml(server.type.nest)} / ${escapeHtml(server.type.egg)}</small></div>
           <div class="detail-card"><span>Owner</span><strong>${escapeHtml(server.owner)}</strong><small>Node: ${escapeHtml(server.node)}</small></div>
           <div class="detail-card"><span>Durum</span><strong>${escapeHtml(statusMeta.label)}</strong><small>Ham durum: ${escapeHtml(server.status)}</small></div>
           <div class="detail-card"><span>CPU Limiti</span><strong>${formatCpuLimit(server.limits.cpu)}</strong><small>Anlik: ${server.metrics.cpu_percent.toFixed(1)}%</small></div>
           <div class="detail-card"><span>RAM Limiti</span><strong>${formatMegabytes(server.limits.memory_mb)}</strong><small>${formatBytes(server.metrics.memory_bytes)} kullaniliyor</small></div>
           <div class="detail-card"><span>Disk Limiti</span><strong>${formatMegabytes(server.limits.disk_mb)}</strong><small>${formatBytes(server.metrics.disk_bytes)} kullaniliyor</small></div>
           <div class="detail-card"><span>Ag Trafegi</span><strong>${formatBytes(networkTotal)}</strong><small>RX ${formatBytes(server.metrics.network_rx_bytes)} | TX ${formatBytes(server.metrics.network_tx_bytes)}</small></div>
           <div class="detail-card"><span>Uptime</span><strong>${formatUptime(server.metrics.uptime)}</strong><small>Sunucunun acik kaldigi sure</small></div>
           <div class="detail-card"><span>Trend Ozeti</span><strong>CPU ${getPeakMetric(server,"cpu_percent").toFixed(1)}%</strong><small>RAM ${getPeakMetric(server,"memory_percent").toFixed(1)}% | Disk ${getPeakMetric(server,"disk_percent").toFixed(1)}%</small></div>
          </div>
          ${renderDetailHistory(server)}
          ${server.description?`<p class="detail-description">${escapeHtml(server.description)}</p>`:""}
        </div>
      </td>
    </tr>
   `;
 }).join("");
 bindTableActions();
 renderPagination(servers.length,totalPages);
}

function bindTableActions(){
 document.querySelectorAll("[data-toggle-server]").forEach((button)=>{
  button.addEventListener("click",()=>{
   const identifier=button.dataset.toggleServer;
   if(!identifier){return;}
   if(state.expanded.has(identifier)){state.expanded.delete(identifier);}else{state.expanded.add(identifier);}
   renderTable(getFilteredServers(state.payload.data.servers));
  });
 });
}

function renderPagination(totalItems,totalPages){
 if(!tablePagination){return;}
 tablePagination.innerHTML=`
  <div class="pagination-copy">${totalItems} sunucu icinden ${Math.min((state.page-1)*state.pageSize+1,totalItems||0)}-${Math.min(state.page*state.pageSize,totalItems)} arasi gosteriliyor</div>
  <div class="pagination-actions">
    <button class="segment-button" data-page-action="prev" ${state.page<=1?"disabled":""}>Onceki</button>
    <span class="pagination-label">Sayfa ${state.page} / ${totalPages}</span>
    <button class="segment-button" data-page-action="next" ${state.page>=totalPages?"disabled":""}>Sonraki</button>
  </div>
 `;
 tablePagination.querySelectorAll("[data-page-action]").forEach((button)=>{
  button.addEventListener("click",()=>{
   if(button.dataset.pageAction==="prev"&&state.page>1){state.page-=1;}
   if(button.dataset.pageAction==="next"&&state.page<totalPages){state.page+=1;}
   renderTable(getFilteredServers(state.payload.data.servers));
  });
 });
}

function renderTrend(servers){
 if(!chartCanvas||!chartContext||!trendList){return;}
 const selected=[...servers].sort((left,right)=>getMetricValue(right,state.trendMetric)-getMetricValue(left,state.trendMetric)).slice(0,5);
 if(trendTitle){trendTitle.textContent=`${getMetricLabel(state.trendMetric)} trendi gosterilen ilk 5 sunucu`;}
 if(!selected.length){
  chartContext.clearRect(0,0,chartCanvas.width,chartCanvas.height);
  trendList.innerHTML=`<div class="trend-item"><div class="trend-copy"><strong>Trend verisi yok</strong><small>Filtreyi degistirerek grafik verisi gorebilirsin.</small></div></div>`;
  return;
 }
 const palette=["#ff7a59","#60a5fa","#34d399","#fbbf24","#f472b6"];
 const width=chartCanvas.width;
 const height=chartCanvas.height;
 const padding=56;
 const graphWidth=width-(padding*2);
 const graphHeight=height-(padding*2);
 chartContext.clearRect(0,0,width,height);
 chartContext.fillStyle="#08111f";
 chartContext.fillRect(0,0,width,height);
 chartContext.strokeStyle="rgba(148, 163, 184, 0.14)";
 chartContext.lineWidth=1;
 for(let i=0;i<=4;i+=1){
  const y=padding+((graphHeight/4)*i);
  chartContext.beginPath();
  chartContext.moveTo(padding,y);
  chartContext.lineTo(width-padding,y);
  chartContext.stroke();
  chartContext.fillStyle="rgba(191, 219, 254, 0.55)";
  chartContext.font="12px Segoe UI";
  chartContext.fillText(`${100-(i*25)}%`,12,y+4);
 }
 trendList.innerHTML=selected.map((server,index)=>{
  const points=getTrendPoints(server);
  return `<div class="trend-item"><span class="trend-swatch" style="background:${palette[index%palette.length]}"></span><div class="trend-copy"><strong>${escapeHtml(server.name)}</strong><small>${escapeHtml(server.type.label)} | Su an ${getMetricValue(server,state.trendMetric).toFixed(1)}%</small><small>En yuksek ${getPeakMetric(server,state.trendMetric).toFixed(1)}% | ${points.length} ornek</small></div></div>`;
 }).join("");
 selected.forEach((server,index)=>{
  const points=getTrendPoints(server);
  if(!points.length){return;}
  chartContext.shadowColor=palette[index%palette.length];
  chartContext.shadowBlur=8;
  chartContext.strokeStyle=palette[index%palette.length];
  chartContext.lineWidth=3;
  chartContext.beginPath();
  points.forEach((point,pointIndex)=>{
   const x=padding+((graphWidth*pointIndex)/Math.max(points.length-1,1));
   const y=padding+graphHeight-(((point[state.trendMetric]||0)/100)*graphHeight);
   if(pointIndex===0){chartContext.moveTo(x,y);}else{chartContext.lineTo(x,y);}
  });
  chartContext.stroke();
  chartContext.shadowBlur=0;
 });
}

function getTrendPoints(server){
 if(!server.trend.length){return [];}
 if(state.trendWindow==="all"){return server.trend;}
 return server.trend.slice(-Number.parseInt(state.trendWindow,10));
}

function getMetricLabel(metric){
 if(metric==="memory_percent"){return "RAM";}
 if(metric==="disk_percent"){return "Disk";}
 return "CPU";
}

function getMetricValue(server,metric){
 if(metric==="memory_percent"){return server.metrics.memory_percent;}
 if(metric==="disk_percent"){return server.metrics.disk_percent;}
 return server.metrics.cpu_percent;
}

function getPeakMetric(server,metric){
 return server.trend.reduce((peak,point)=>Math.max(peak,point[metric]||0),0);
}

function renderDetailHistory(server){
 const historyCards=[
  {label:"CPU Gecmisi",metric:"cpu_percent",tone:"cpu"},
  {label:"RAM Gecmisi",metric:"memory_percent",tone:"memory"},
  {label:"Disk Gecmisi",metric:"disk_percent",tone:"disk"},
 ];
 return `
  <div class="detail-history-grid">
    ${historyCards.map((card)=>{
      const points=getTrendPoints(server);
      const current=points.length?(points[points.length-1][card.metric]||0):0;
      return `
        <div class="history-card">
          <div class="history-head">
            <span>${escapeHtml(card.label)}</span>
            <strong>${current.toFixed(1)}%</strong>
          </div>
          ${renderSparkline(points,card.metric,card.tone)}
          <small>En yuksek ${getPeakMetric(server,card.metric).toFixed(1)}% | ${points.length} ornek</small>
        </div>
      `;
    }).join("")}
  </div>
 `;
}

function renderSparkline(points,metric,tone){
 if(!points.length){
  return `<div class="history-empty">Kayit bulunamadi</div>`;
 }
 const width=280;
 const height=88;
 const padding=10;
 const step=points.length>1?(width-(padding*2))/(points.length-1):0;
 const d=points.map((point,index)=>{
  const x=padding+(step*index);
  const y=padding+((100-(point[metric]||0))/100)*(height-(padding*2));
  return `${index===0?"M":"L"} ${x.toFixed(2)} ${y.toFixed(2)}`;
 }).join(" ");
 return `
  <svg class="history-svg ${escapeHtml(tone)}" viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" aria-hidden="true">
    <path class="history-grid-line" d="M ${padding} ${height/2} L ${width-padding} ${height/2}"></path>
    <path class="history-line" d="${d}"></path>
  </svg>
 `;
}

function getNetworkTotal(server){
 return server.metrics.network_rx_bytes+server.metrics.network_tx_bytes;
}

function normalizeStatus(status){
 const installingStates=["starting","stopping","transition"];
 const closedStates=["offline","missing","unknown","untracked","restricted"];
 if(closedStates.includes(status)){return{label:"Kapali",tone:"offline"};}
 if(installingStates.includes(status)){return{label:"Kuruluyor",tone:"installing"};}
 return{label:"Acik",tone:"running"};
}

function getStatusMeta(server){
 const base=normalizeStatus(server.status);
 if(base.tone==="installing"){
  const hasLiveSignals=server.metrics.uptime>0||server.metrics.memory_bytes>0||server.metrics.network_rx_bytes>0||server.metrics.network_tx_bytes>0;
  if(hasLiveSignals){return{label:"Acik",tone:"running"};}
 }
 return base;
}

function formatBytes(bytes){
 if(!bytes||bytes<=0){return "0 B";}
 const sizes=["B","KB","MB","GB","TB"];
 const index=Math.min(Math.floor(Math.log(bytes)/Math.log(1024)),sizes.length-1);
 const value=bytes/(1024**index);
 return `${value.toFixed(value>=100?0:1)} ${sizes[index]}`;
}

function formatMegabytes(megabytes){return formatBytes((megabytes||0)*1024*1024);}

function formatCpuLimit(cpuLimit){
 if(!cpuLimit||cpuLimit<=0){return "0";}
 const cores=cpuLimit/100;
 return Number.isInteger(cores)?`${cores}`:cores.toFixed(1);
}

function formatUptime(seconds){
 if(!seconds||seconds<=0){return "0 dk";}
 const days=Math.floor(seconds/86400);
 const hours=Math.floor((seconds%86400)/3600);
 const minutes=Math.floor((seconds%3600)/60);
 if(days>0){return `${days}g ${hours}sa`;}
 if(hours>0){return `${hours}sa ${minutes}dk`;}
 return `${minutes} dk`;
}

function showError(message){
 if(!errorBanner){return;}
 errorBanner.textContent=message;
 errorBanner.classList.remove("hidden");
}

function hideError(){
 if(!errorBanner){return;}
 errorBanner.textContent="";
 errorBanner.classList.add("hidden");
}

function escapeHtml(value){
 return String(value).replaceAll("&","&amp;").replaceAll("<","&lt;").replaceAll(">","&gt;").replaceAll('"',"&quot;").replaceAll("'","&#39;");
}

if(searchInput){
 searchInput.addEventListener("input",(event)=>{state.query=event.target.value;state.page=1;if(state.payload){renderDashboard();}});
}
if(statusFilter){
 statusFilter.addEventListener("change",(event)=>{state.status=event.target.value;state.page=1;renderDashboard();});
}
if(familyFilter){
 familyFilter.addEventListener("change",(event)=>{state.family=event.target.value;state.page=1;renderDashboard();});
}
if(nodeFilter){
 nodeFilter.addEventListener("change",(event)=>{state.node=event.target.value;state.page=1;renderDashboard();});
}
if(sortSelect){
 sortSelect.addEventListener("change",(event)=>{state.sort=event.target.value;state.page=1;renderDashboard();});
}
document.addEventListener("click",(event)=>{
 const metricButton=event.target.closest("[data-trend-metric]");
 if(metricButton){state.trendMetric=metricButton.dataset.trendMetric;renderDashboard();}
 const windowButton=event.target.closest("[data-trend-window]");
 if(windowButton){state.trendWindow=windowButton.dataset.trendWindow;renderDashboard();}
});
if(refreshButton){
 refreshButton.addEventListener("click",()=>{loadOverview();});
}
if(menuToggle&&sideMenu&&menuOverlay){
 menuToggle.addEventListener("click",()=>{sideMenu.classList.toggle("open");menuOverlay.classList.toggle("hidden");});
 menuOverlay.addEventListener("click",()=>{sideMenu.classList.remove("open");menuOverlay.classList.add("hidden");});
}

loadOverview();
