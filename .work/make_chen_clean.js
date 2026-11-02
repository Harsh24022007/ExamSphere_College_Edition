const fs=require('fs');const W=5200,H=3600;let s=[];const E=t=>String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function t(x,y,v,c='txt',a='middle'){s.push(`<text class="${c}" x="${x}" y="${y}" text-anchor="${a}">${E(v)}</text>`)}
function l(x1,y1,x2,y2,c='line'){s.push(`<line class="${c}" x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"/>`)}
function box(x,y,w,h,n,sub=''){s.push(`<rect class="entity" x="${x}" y="${y}" width="${w}" height="${h}"/>`);t(x+w/2,y+h/2+(sub? -8:8),n,'entityText');if(sub)t(x+w/2,y+h/2+28,sub,'sub') ;return{x,y,w,h,cx:x+w/2,cy:y+h/2}}
function dia(x,y,w,h,n){s.push(`<polygon class="diamond" points="${x},${y-h/2} ${x+w/2},${y} ${x},${y+h/2} ${x-w/2},${y}"/>`);t(x,y+8,n,'rel');return{x,y,w,h}}
function oval(x,y,w,h,lines,pk=false){s.push(`<ellipse class="${pk?'pk ':''}oval" cx="${x}" cy="${y}" rx="${w/2}" ry="${h/2}"/>`);lines.forEach((v,i)=>t(x,y+(i-(lines.length-1)/2)*24,v,'attr'));return{x,y}}
function c(x,y,v){t(x,y,v,'card')}
function panel(x,y,w,h,title,rows){s.push(`<rect class="panel" x="${x}" y="${y}" width="${w}" height="${h}"/>`);t(x+25,y+38,title,'panelTitle','start');rows.forEach((r,i)=>{let yy=y+78+i*34;s.push(`<ellipse class="smallOval" cx="${x+105}" cy="${yy}" rx="85" ry="22"/>`);t(x+105,yy+6,r[0],'smallAttr');t(x+220,yy+6,r[1],'smallText','start')})}
function section(x,y,w,title){s.push(`<rect class="section" x="${x}" y="${y}" width="${w}" height="70"/>`);t(x+30,y+45,title,'sectionTitle','start')}
s.push(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}"><rect width="100%" height="100%" fill="white"/><style>
.title{font:700 52px Arial;fill:#000}.subtitle{font:24px Arial;fill:#222}.section{fill:#fff;stroke:#000;stroke-width:2}.sectionTitle{font:700 28px Arial;fill:#000}.entity{fill:#fff;stroke:#000;stroke-width:5}.entityText{font:700 26px Arial;fill:#000}.sub{font:18px Arial;fill:#000}.diamond{fill:#fff;stroke:#000;stroke-width:5}.rel{font:700 20px Arial;fill:#000}.oval{fill:#fff;stroke:#000;stroke-width:4}.pk{stroke-width:6}.attr{font:18px Arial;fill:#000}.line{stroke:#000;stroke-width:3;fill:none}.card{font:700 20px Arial;fill:#000}.panel{fill:#fff;stroke:#000;stroke-width:3}.panelTitle{font:700 23px Arial;fill:#000}.smallOval{fill:#fff;stroke:#000;stroke-width:2}.smallAttr{font:14px Arial;fill:#000}.smallText{font:16px Arial;fill:#000}.legend{font:18px Arial;fill:#000}.foot{font:16px Arial;fill:#000}
</style>`);
t(2600,65,'ExamSphere College Edition — Chen ER Diagram','title');t(2600,105,'Clean black-and-white logical model | PK = double-underlined attribute | 1 / N / 0..1 = cardinality','subtitle');
section(120,150,4960,'ACADEMIC STRUCTURE');
let dept=box(300,330,320,90,'DEPARTMENT');let sub=box(1250,330,320,90,'SUBJECT');let student=box(300,720,320,90,'STUDENTS');let exam=box(2200,330,360,90,'EXAMINATION');let reg=box(2200,720,430,90,'EXAM_REGISTRATION');
let offers=dia(930,375,220,100,'OFFERS');l(620,375,820,375);l(1040,375,1250,375);c(700,350,'1');c(1120,350,'N');
let belongs=dia(930,765,220,100,'BELONGS_TO');l(620,375,760,725);l(1040,765,620,765);l(300,420,300,720);c(670,650,'1');c(700,815,'N');
let sched=dia(1880,375,250,100,'SCHEDULES');l(1570,375,1755,375);l(2005,375,2200,375);c(1610,350,'1');c(2110,350,'N');
let accepts=dia(2820,610,250,100,'ACCEPTS');l(2380,420,2700,580);l(2945,610,2415,720);c(2500,490,'1');c(2710,700,'N');
let registers=dia(1430,765,260,100,'REGISTERS');l(620,765,1300,765);l(1560,765,2200,765);c(700,740,'1');c(1770,740,'N');
// attributes academic
oval(300,260,190,58,['DEPT_ID (PK)'],true);l(300,330,300,289);oval(620,250,190,58,['DEPT_NAME']);l(500,330,620,279);
oval(1250,260,210,58,['SUBJECT_ID (PK)'],true);l(1250,330,1250,289);oval(1570,250,210,58,['SUBJECT_NAME']);l(1500,330,1570,279);oval(1780,470,230,58,['OFFERED_SEMESTER']);l(1420,420,1730,470);oval(1780,540,180,58,['DEPT_ID (FK)']);l(1500,420,1730,540);
oval(300,940,190,58,['STUDENT_ID (PK)'],true);l(300,810,300,911);oval(620,940,250,58,['FNAME / MNAME / LNAME']);l(500,810,620,911);oval(150,690,220,58,['CURRENT_SEMESTER']);l(300,720,190,690);oval(620,690,180,58,['DEPT_ID (FK)']);l(500,720,620,690);
oval(2200,250,180,58,['EXAM_ID (PK)'],true);l(2200,330,2200,279);oval(2520,250,180,58,['EXAM_DATE']);l(2450,330,2520,279);oval(2700,430,250,58,['START_TIME / END_TIME']);l(2500,375,2650,430);oval(2600,510,150,58,['published']);l(2380,420,2550,510);
oval(2200,940,210,58,['REGISTR_ID (PK)'],true);l(2200,810,2200,911);oval(2500,940,200,58,['STUDENT_ID (FK)']);l(2450,810,2500,911);oval(2760,840,170,58,['EXAM_ID (FK)']);l(2630,765,2690,840);oval(2760,940,180,58,['REGISTR_DATE']);l(2630,810,2700,940);
section(120,1100,4960,'EXAM OPERATIONS');
let rooms=box(300,1280,320,90,'ROOMS');let alloc=box(1500,1280,400,90,'ROOM_ALLOCATION');let duties=box(2850,1280,400,90,'INVIGILATOR_DUTY');let inv=box(2850,1780,350,90,'INVIGILATOR');
let assigned=dia(1080,1325,270,100,'ASSIGNED_SEAT');l(620,1325,945,1325);l(1215,1325,1500,1325);c(700,1300,'1');c(1370,1300,'0..1');
let uses=dia(2280,1325,220,100,'USES');l(1900,1325,2170,1325);l(2390,1325,2850,1325);c(1970,1300,'N');c(2670,1300,'1');
let serves=dia(3000,1550,260,100,'ASSIGNED_FOR');l(3030,1370,3030,1500);l(2970,1600,3020,1780);c(3100,1420,'1');c(3090,1700,'N');
let hosts=dia(1650,1650,220,100,'HOSTS');l(460,1370,1540,1650);l(1760,1650,2850,1370);c(700,1450,'1');c(2300,1600,'N');
// attributes ops
oval(300,1210,170,58,['ROOM_ID (PK)'],true);l(300,1280,300,1239);oval(620,1210,210,58,['BUILDING / FLOOR']);l(500,1280,620,1239);oval(140,1430,160,58,['CAPACITY']);l(300,1370,180,1430);oval(520,1500,140,58,['STATUS']);l(450,1370,520,1500);
oval(1500,1160,220,58,['ALLOCATION_ID (PK)'],true);l(1500,1280,1500,1189);oval(1850,1160,190,58,['REGISTR_ID (FK)']);l(1750,1280,1850,1189);oval(1500,1480,170,58,['ROOM_ID (FK)']);l(1650,1370,1530,1480);oval(1850,1480,130,58,['SEAT_NO']);l(1780,1370,1820,1480);
oval(2850,1160,160,58,['DUTY_ID (PK)'],true);l(2850,1280,2850,1189);oval(3190,1160,160,58,['EXAM_ID (FK)']);l(3100,1280,3170,1189);oval(2780,1450,240,58,['INVIGILATOR_ID (FK)']);l(2930,1370,2800,1450);oval(3210,1450,160,58,['ROOM_ID (FK)']);l(3170,1370,3190,1450);
oval(2850,1900,230,58,['INVIGILATOR_ID (PK)'],true);l(3000,1870,2920,1900);oval(3240,1900,250,58,['NAME / PHONE / EMAIL']);l(3140,1870,3200,1900);
// lower panels, clean attribute reference
section(120,2130,4960,'ACCOUNT AND OPTIONAL ATTENDANCE');
let users=box(500,2360,320,90,'users','login account');let admins=box(1900,2360,360,90,'ADMIN_USERS','admin auth');let attendance=box(3300,2360,400,90,'attendance','optional extension');
let stuAcc=dia(1100,2405,250,100,'HAS_ACCOUNT');l(620,810,950,2350);l(1250,2405,500,2405);c(760,1140,'0..1');c(1350,2380,'0..1');
let invAcc=dia(2500,2250,250,100,'HAS_ACCOUNT');l(3020,1870,2600,2200);l(2375,2250,2260,2360);c(2800,2020,'0..1');c(2350,2320,'0..1');
let checked=dia(2800,2700,220,100,'CHECKS');l(820,2405,2690,2700);l(2910,2700,3480,2450);c(1120,2480,'0..1');c(3190,2580,'N');
let recorded=dia(2500,3000,260,100,'RECORDED_FOR');l(1700,1370,2380,2950);l(2630,3000,3500,2450);c(1900,1800,'1');c(3150,2940,'N');
// account attrs
oval(500,2580,150,58,['user_id (PK)'],true);l(500,2450,500,2551);oval(850,2580,190,58,['username / role']);l(700,2450,850,2551);oval(650,2780,230,58,['student_id / invigilator_id']);l(700,2450,700,2751);
oval(1900,2580,190,58,['ADMIN_ID (PK)'],true);l(1900,2450,1900,2551);oval(2260,2580,250,58,['USERNAME / PASSWORD_HASH']);l(2200,2450,2260,2551);
oval(3300,2580,220,58,['attendance_id (PK)'],true);l(3300,2450,3300,2551);oval(3700,2580,260,58,['allocation_id / checked_by']);l(3650,2450,3700,2551);
// legend
s.push(`<rect class="panel" x="120" y="3180" width="4960" height="260"/>`);t(160,3225,'LEGEND','panelTitle','start');s.push(`<rect class="entity" x="180" y="3270" width="150" height="55"/>`);t(360,3305,'Entity / table','legend','start');s.push(`<polygon class="diamond" points="700,3298 770,3268 840,3298 770,3328"/>`);t(875,3305,'Relationship','legend','start');s.push(`<ellipse class="oval" cx="1210" cy="3298" rx="90" ry="30"/>`);t(1330,3305,'Attribute','legend','start');s.push(`<ellipse class="pk oval" cx="1700" cy="3298" rx="110" ry="30"/>`);t(1840,3305,'Primary key attribute','legend','start');t(2600,3305,'1 = one   N = many   0..1 = optional one','legend','start');t(160,3375,'Note: attendance is an optional existing-deployment table referenced by the migration; its complete CREATE TABLE definition is not included in db_setup.sql.','foot','start');s.push('</svg>');
fs.writeFileSync('outputs/examsphere_er_diagram_clean_bw.svg',s.join('\n'),'utf8');
