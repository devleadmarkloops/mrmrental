<?php
// index.php — Landing Page (Search Form Only)
$checkIn  = $_GET['checkIn']  ?? '';
$checkOut = $_GET['checkOut'] ?? '';
$guests   = $_GET['guests']   ?? '';
$city     = $_GET['city']     ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MRMVR — Vacation Rentals</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f5f5f5;color:#333;min-height:100vh;display:flex;flex-direction:column;justify-content:center}
.hero{text-align:center;padding:60px 20px}
.hero h1{font-size:56px;font-weight:700;color:#333;letter-spacing:2px;margin-bottom:40px}
.search-bar{max-width:960px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:50px;display:flex;align-items:center;padding:8px 8px 8px 24px;box-shadow:0 2px 12px rgba(0,0,0,.06);position:relative}
.search-field{flex:1;min-width:0;padding:0 16px}
.search-field label{display:block;font-size:11px;font-weight:600;color:#333;margin-bottom:4px;text-transform:uppercase;letter-spacing:.5px}
.search-field input,.search-field select{width:100%;border:none;outline:none;font-size:14px;color:#666;background:transparent;padding:2px 0;cursor:pointer}
.search-field input::placeholder{color:#aaa}
.search-divider{width:1px;height:36px;background:#e0e0e0;flex-shrink:0}
.search-btn{background:#e53e3e;color:#fff;border:none;border-radius:50px;padding:14px 28px;font-size:15px;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0;transition:background .2s}
.search-btn:hover{background:#c53030}
.destination-wrapper{position:relative}
.destination-wrapper .icon{position:absolute;left:0;top:50%;transform:translateY(-50%);color:#999;font-size:16px;pointer-events:none}
.destination-wrapper input{padding-left:22px!important}
.city-dropdown{display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e0e0e0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);max-height:220px;overflow-y:auto;z-index:1000;margin-top:8px}
.city-dropdown .city-item{padding:10px 16px;cursor:pointer;font-size:14px;color:#333}
.city-dropdown .city-item:hover{background:#f5f5f5}
.date-trigger{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;color:#666;user-select:none;padding:2px 0}
.date-trigger .cal-icon{color:#999;font-size:16px}
.date-trigger .arrow{color:#999;font-size:12px;margin-left:auto}
.calendar-popup{display:none;position:absolute;top:calc(100% + 12px);left:50%;transform:translateX(-50%);background:#fff;border:1px solid #e0e0e0;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);padding:24px;z-index:1001;width:620px}
.calendar-popup.open{display:block}
.calendar-nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.calendar-nav button{background:none;border:none;font-size:20px;cursor:pointer;color:#333;padding:4px 8px;border-radius:50%}
.calendar-nav button:hover{background:#f0f0f0}
.calendar-months{display:flex;gap:32px}
.cal-month{flex:1}
.cal-month-title{text-align:center;font-weight:600;font-size:15px;color:#333;margin-bottom:12px}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:0;text-align:center}
.cal-grid .day-header{font-size:12px;color:#999;font-weight:500;padding:4px 0 8px}
.cal-grid .day-cell{padding:8px 2px;font-size:14px;cursor:pointer;aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:0}
.cal-grid .day-cell:hover:not(.empty):not(.past){background:#e8f4fd}
.cal-grid .day-cell.empty{cursor:default}
.cal-grid .day-cell.past{color:#ccc;cursor:default;text-decoration:line-through}
.cal-grid .day-cell.today{font-weight:700;border:2px solid #4a90d9;border-radius:50%}
.cal-grid .day-cell.selected-start,.cal-grid .day-cell.selected-end{background:#4a90d9;color:#fff;border-radius:50%;font-weight:600;z-index:1}
.cal-grid .day-cell.in-range{background:#e8f4fd;color:#333}
.guests-select{appearance:none;-webkit-appearance:none;background:transparent;padding-right:16px!important}
@media(max-width:768px){
    .hero h1{font-size:36px}
    .search-bar{flex-direction:column;border-radius:16px;padding:16px;gap:12px}
    .search-divider{width:100%;height:1px}
    .search-field{padding:8px 0}
    .search-btn{width:100%}
    .calendar-popup{width:95vw;left:50%;padding:16px}
    .calendar-months{flex-direction:column;gap:20px}
}
</style>
</head>
<body>
<div class="hero">
    <h1>MRMVR</h1>
    <form class="search-bar" id="searchForm" method="GET" action="all.php">
        <div class="search-field destination-wrapper">
            <label>Destination</label>
            <span class="icon">&#9737;</span>
            <input type="text" id="destinationInput" name="city" placeholder="Where to?" autocomplete="off" value="<?= htmlspecialchars($city) ?>">
            <div class="city-dropdown" id="cityDropdown"></div>
        </div>
        <div class="search-divider"></div>
        <div class="search-field">
            <label>Check-in / check-out</label>
            <div class="date-trigger" id="dateTrigger">
                <span class="cal-icon">&#128197;</span>
                <span id="dateLabel"><?= ($checkIn && $checkOut) ? htmlspecialchars(date('M j', strtotime($checkIn)) . ' – ' . date('M j', strtotime($checkOut))) : 'Add dates' ?></span>
                <span class="arrow">&#9662;</span>
            </div>
            <input type="hidden" name="checkIn"  id="checkInInput"  value="<?= htmlspecialchars($checkIn) ?>">
            <input type="hidden" name="checkOut" id="checkOutInput" value="<?= htmlspecialchars($checkOut) ?>">
        </div>
        <div class="search-divider"></div>
        <div class="search-field">
            <label>Guests</label>
            <select name="guests" class="guests-select" id="guestsSelect">
                <option value="">Any</option>
                <?php for ($i = 1; $i <= 16; $i++): ?>
                    <option value="<?= $i ?>"<?= ($guests == $i) ? ' selected' : ''?>><?= $i ?> Guest<?= $i > 1 ? 's' : '' ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="search-btn">Search</button>
        <div class="calendar-popup" id="calendarPopup">
            <div class="calendar-nav">
                <button type="button" id="calPrev">&lsaquo;</button>
                <div class="calendar-months" id="calendarMonths"></div>
                <button type="button" id="calNext">&rsaquo;</button>
            </div>
        </div>
    </form>
</div>

<script>
(function(){
    const destInput=document.getElementById('destinationInput'),cityDropdown=document.getElementById('cityDropdown');
    let debounceTimer=null;
    destInput.addEventListener('input',function(){
        clearTimeout(debounceTimer);const q=this.value.trim();
        if(q.length<2){cityDropdown.style.display='none';return}
        debounceTimer=setTimeout(()=>{
            fetch('cities_api.php?searchText='+encodeURIComponent(q)+'&limit=10').then(r=>r.json()).then(cities=>{
                cityDropdown.innerHTML='';if(!cities.length){cityDropdown.style.display='none';return}
                cities.forEach(c=>{const div=document.createElement('div');div.className='city-item';div.textContent=c;div.addEventListener('click',()=>{destInput.value=c;cityDropdown.style.display='none'});cityDropdown.appendChild(div)});
                cityDropdown.style.display='block';
            }).catch(()=>{cityDropdown.style.display='none'});
        },300);
    });
    document.addEventListener('click',e=>{if(!e.target.closest('.destination-wrapper'))cityDropdown.style.display='none'});

    const calPopup=document.getElementById('calendarPopup'),dateTrigger=document.getElementById('dateTrigger'),dateLabel=document.getElementById('dateLabel');
    const checkInField=document.getElementById('checkInInput'),checkOutField=document.getElementById('checkOutInput');
    const calMonths=document.getElementById('calendarMonths'),prevBtn=document.getElementById('calPrev'),nextBtn=document.getElementById('calNext');
    const today=new Date();today.setHours(0,0,0,0);
    let baseMonth=today.getMonth(),baseYear=today.getFullYear();
    function parseYMD(s){const[y,m,d]=s.split('-').map(Number);return new Date(y,m-1,d)}
    let startDate=checkInField.value?parseYMD(checkInField.value):null;
    let endDate=checkOutField.value?parseYMD(checkOutField.value):null;
    if(startDate){baseMonth=startDate.getMonth();baseYear=startDate.getFullYear()}
    function fmtYMD(d){return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0')}
    function fmtLabel(d){const m=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];return m[d.getMonth()]+' '+d.getDate()}
    function renderCalendar(){
        calMonths.innerHTML='';
        for(let o=0;o<2;o++){
            let m=baseMonth+o,y=baseYear;if(m>11){m-=12;y++}
            const md=document.createElement('div');md.className='cal-month';
            const mn=['January','February','March','April','May','June','July','August','September','October','November','December'];
            md.innerHTML='<div class="cal-month-title">'+mn[m]+' '+y+'</div>';
            const g=document.createElement('div');g.className='cal-grid';
            ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].forEach(d=>{const h=document.createElement('div');h.className='day-header';h.textContent=d;g.appendChild(h)});
            const first=new Date(y,m,1).getDay(),dim=new Date(y,m+1,0).getDate();
            for(let i=0;i<first;i++){const e=document.createElement('div');e.className='day-cell empty';g.appendChild(e)}
            for(let d=1;d<=dim;d++){
                const cell=document.createElement('div');cell.className='day-cell';cell.textContent=d;
                const cd=new Date(y,m,d);cd.setHours(0,0,0,0);
                if(cd<today){cell.classList.add('past')}else{
                    if(cd.getTime()===today.getTime())cell.classList.add('today');
                    if(startDate&&cd.getTime()===startDate.getTime())cell.classList.add('selected-start');
                    if(endDate&&cd.getTime()===endDate.getTime())cell.classList.add('selected-end');
                    if(startDate&&endDate&&cd>startDate&&cd<endDate)cell.classList.add('in-range');
                    cell.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();handleDateClick(cd)});
                }
                g.appendChild(cell);
            }
            md.appendChild(g);calMonths.appendChild(md);
        }
    }
    function handleDateClick(date){
        if(!startDate||(startDate&&endDate)||date<startDate){
            startDate=date;endDate=null;checkInField.value=fmtYMD(date);checkOutField.value='';dateLabel.textContent=fmtLabel(date)+' – ...';
        }else{
            endDate=date;checkOutField.value=fmtYMD(date);dateLabel.textContent=fmtLabel(startDate)+' – '+fmtLabel(endDate);
            setTimeout(()=>{calPopup.classList.remove('open')},200);
        }
        renderCalendar();
    }
    dateTrigger.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();calPopup.classList.toggle('open');renderCalendar()});
    prevBtn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();baseMonth--;if(baseMonth<0){baseMonth=11;baseYear--}renderCalendar()});
    nextBtn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();baseMonth++;if(baseMonth>11){baseMonth=0;baseYear++}renderCalendar()});
    document.addEventListener('click',function(e){if(!e.target.closest('.calendar-popup')&&!e.target.closest('#dateTrigger'))calPopup.classList.remove('open')});
    renderCalendar();
})();
</script>
</body>
</html>
