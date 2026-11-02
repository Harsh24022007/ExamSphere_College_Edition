const fs=require('fs');const W=6000,H=4000;let s=[];const esc=x=>String(x).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const css=`<style>
*{font-family:Arial,Helvetica,sans-serif}.bg{fill:#fff}.title{font-size:58px;font-weight:700;fill:#111}.sub{font-size:25px;fill:#555}.section{fill:#111}.sectionText{font-size:25px;font-weight:700;fill:#fff;letter-spacing:2px}.entity{fill:#fff;stroke:#111;stroke-width:6}.entityText{font-size:27px;font-weight:700;fill:#111}.entitySub{font-size:18px;fill:#555}.diamond{fill:#fff;stroke:#111;stroke-width:6}.relText{font-size:21px;font-weight:700;fill:#111}.line{fill:none;stroke:#111;stroke-width:4}.thin{fill:none;stroke:#555;stroke-width:2}.card{font-size:22px;font-weight:700;fill:#111}.oval{fill:#fff;stroke:#111;stroke-width:4}.ovalPK{fill:#fff;stroke:#111;stroke-width:6}.attr{font-size:18px;fill:#111}.small{font-size:17px;fill:#333}.tableHead{font-size:21px;font-weight:700;fill:#111}.tableText{font-size:18px;fill:#222}.note{font-size:17px;fill:#555}.legend{font-size:19px;fill:#222}
</style>`;
function txt(x,y,v,c='small',a='middle'){s.push(`<text x="${x}" y="${y}" class="${c}" text-anchor="${a}">${esc(v)}</text>`)}
function line(x1,y1,x2,y2,c='line'){s.push(`<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" class="${c}"/>`)}
function ent(x,y,w,h,n,sub=''){s.push(`<rect x="${x}" y="${y}" width="${w}" height="${h}" class="entity"/>`);txt(x+w/2,y+h/2+(sub?-8:9),n,'entityText');if(sub)txt(x+w/2,y+h/2+28,sub,'entitySub');return{x,y,w,h,cx:x+w/2,cy:y+h/2}}
function rel(x,y,w,h,n){s.push(`<polygon points="${x},${y-h/2} ${x+w/2},${y} ${x},${y+h/2} ${x-w/2},${y}" class="diamond"/>`);txt(x,y+8,n,'relText');return{x,y,w,h}}
function oval(x,y,w,h,n,pk=false){s.push(`<ellipse cx="${x}" cy="${y}" rx="${w/2}" ry="${h/2}" class="${pk?'ovalPK':'oval'}"/>`);txt(x,y+6,n,'attr');}
function card(x,y,n){txt(x,y,n,'card')}
function label(x,y,n){txt(x,y,n,'small','start')}
function section(y,n){s.push(`<rect x="180" y="${y}" width="5640" height="60" class="section"/>`);txt(220,y+40,n,'sectionText','start')}
function attrTable(x,y,w,h,name,attrs){s.push(`<rect x="${x}" y="${y}" width="${w}" height="${h}" class="entity"/>`);txt(x+22,y+34,name,'tableHead','start');let yy=y+70;attrs.forEach(a=>{txt(x+25,yy,a,'tableText','start');yy+=31})}
// canvas
s.push(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">${css}<rect width="100%" height="100%" class="bg"/>`);
txt(3000,85,'EXAMSPHERE — COLLEGE EXAMINATION MANAGEMENT','title');txt(3000,125,'Conceptual Entity–Relationship Diagram • Standard Chen notation • Black and white','sub');
section(180,'01  ACADEMIC MODEL');
// top clean map
let dept=ent(350,390,420,100,'DEPARTMENT');let rOff=rel(1120,440,240,105,'OFFERS');let subj=ent(1450,390,420,100,'SUBJECT');let rSch=rel(2220,440,260,105,'SCHEDULES');let exam=ent(2590,390,450,100,'EXAMINATION');
line(770,440,1000,440);line(1240,440,1450,440);card(850,410,'1');card(1350,410,'N');line(1870,440,2090,440);line(2350,440,2590,440);card(1950,410,'1');card(2490,410,'N');
let student=ent(350,800,420,100,'STUDENTS');let rReg=rel(1120,850,250,105,'REGISTERS');let reg=ent(1450,800,480,100,'EXAM_REGISTRATION');let rAcc=rel(2220,850,240,105,'ACCEPTS');
line(770,850,995,850);line(1245,850,1450,850);card(850,820,'1');card(1340,820,'N');line(1930,850,2100,850);line(2340,850,1690,490);card(1990,820,'N');card(2400,650,'1');
// attributes as restrained ovals only key identity + important FK
oval(360,300,210,62,'DEPT_ID (PK)',true);line(560,390,450,330);oval(780,300,220,62,'DEPT_NAME');line(650,390,780,330);
oval(1450,300,240,62,'SUBJECT_ID (PK)',true);line(1550,390,1510,330);oval(1810,300,220,62,'DEPT_ID (FK)');line(1770,390,1810,330);
oval(2590,300,210,62,'EXAM_ID (PK)',true);line(2700,390,2670,330);oval(2900,250,220,62,'EXAM_DATE');line(2860,390,2900,280);oval(3200,390,260,62,'START / END TIME');line(3040,440,3070,390);
oval(360,710,230,62,'STUDENT_ID (PK)',true);line(560,800,450,740);oval(770,710,250,62,'DEPT_ID (FK)');line(650,800,770,740);
oval(1450,710,250,62,'REGISTR_ID (PK)',true);line(1600,800,1570,740);oval(1810,710,220,62,'STUDENT_ID (FK)');line(1750,800,1810,740);oval(2080,710,200,62,'EXAM_ID (FK)');line(1840,800,2080,740);
section(1050,'02  EXAM OPERATIONS');
let rooms=ent(350,1270,420,100,'ROOMS');let rSeat=rel(1120,1320,290,105,'ALLOCATES');let alloc=ent(1500,1270,500,100,'ROOM_ALLOCATION');let rDuty=rel(2400,1320,260,105,'ASSIGNED');let duty=ent(2790,1270,500,100,'INVIGILATOR_DUTY');let inv=ent(3900,1270,470,100,'INVIGILATOR');let rInv=rel(3550,1520,250,105,'PERFORMS');
line(770,1320,975,1320);line(1265,1320,1500,1320);card(850,1290,'1');card(1400,1290,'N');line(2000,1320,2270,1320);line(2530,1320,2790,1320);card(2090,1290,'1');card(2690,1290,'N');line(3290,1320,3900,1320);card(3390,1290,'N');card(3800,1290,'1');line(3020,1370,3550,1470);line(3675,1520,4140,1370);card(3200,1420,'N');card(3900,1450,'1');
oval(360,1180,200,62,'ROOM_ID (PK)',true);line(560,1270,460,1210);oval(780,1180,230,62,'CAPACITY');line(650,1270,780,1210);
oval(1500,1170,260,62,'ALLOCATION_ID (PK)',true);line(1660,1270,1600,1200);oval(1830,1170,220,62,'REGISTR_ID (FK)');line(1810,1270,1830,1200);oval(2050,1450,180,62,'SEAT_NO');line(1900,1370,2010,1450);
oval(2790,1170,200,62,'DUTY_ID (PK)',true);line(2920,1270,2900,1200);oval(3160,1170,190,62,'EXAM_ID (FK)');line(3130,1270,3160,1200);oval(3400,1450,250,62,'ROOM_ID (FK)');line(3270,1370,3370,1450);
oval(3900,1170,260,62,'INVIGILATOR_ID (PK)',true);line(4120,1270,4040,1200);oval(4400,1170,250,62,'NAME / CONTACT');line(4300,1270,4370,1200);
section(1850,'03  ACCOUNT AND ATTENDANCE EXTENSIONS');
let users=ent(550,2080,450,100,'users','login account');let rStu=rel(1320,2130,260,105,'AUTHENTICATES');let rStaff=rel(2870,2130,260,105,'AUTHENTICATES');let admin=ent(3350,2080,480,100,'ADMIN_USERS','admin login');let att=ent(4550,2080,500,100,'attendance','optional extension');let rCheck=rel(4200,2450,250,105,'CHECKS');let rRecord=rel(3000,2750,300,105,'RECORDED FOR');
line(1000,2130,1190,2130);line(1450,2130,3350,2130);card(1060,2100,'0..1');card(1570,2100,'0..1');line(3830,2130,4550,2130);card(3970,2100,'0..1');card(4350,2100,'N');line(775,2180,4080,2420);line(4325,2450,4800,2180);card(1400,2290,'0..1');card(4550,2330,'N');line(1750,1370,2850,2700);line(3150,2750,4750,2180);card(2100,1900,'1');card(4200,2650,'N');
oval(550,1980,190,62,'user_id (PK)',true);line(760,2080,650,2010);oval(950,1980,190,62,'role');line(900,2080,950,2010);oval(3350,1980,210,62,'ADMIN_ID (PK)',true);line(3550,2080,3450,2010);oval(3800,1980,230,62,'USERNAME');line(3720,2080,3800,2010);oval(4550,1980,240,62,'attendance_id (PK)',true);line(4750,2080,4650,2010);
// attribute reference, not extra connectors
section(2820,'04  ATTRIBUTE REFERENCE');
attrTable(250,3000,1000,430,'DEPARTMENT',['DEPT_ID (PK)','DEPT_NAME']);attrTable(1350,3000,1000,430,'SUBJECT',['SUBJECT_ID (PK)','SUBJECT_NAME','OFFERED_SEMESTER','DEPT_ID (FK)']);attrTable(2450,3000,1000,430,'STUDENTS',['STUDENT_ID (PK)','FNAME, MNAME, LNAME','CURRENT_SEMESTER','DEPT_ID (FK)']);attrTable(3550,3000,1000,430,'EXAMINATION',['EXAM_ID (PK)','SUBJECT_ID (FK)','EXAM_DATE','START_TIME','END_TIME','published']);attrTable(4650,3000,1050,430,'REGISTRATION / ALLOCATION',['REGISTR_ID, STUDENT_ID, EXAM_ID','ALLOCATION_ID, ROOM_ID, SEAT_NO','REGISTR_DATE']);
// footer
s.push(`<rect x="180" y="3510" width="5640" height="300" class="entity"/>`);txt(220,3550,'NOTATION','tableHead','start');s.push(`<rect x="240" y="3600" width="150" height="60" class="entity"/>`);label(430,3640,'Entity / table');s.push(`<polygon points="760,3630 830,3600 900,3630 830,3660" class="diamond"/>`);label(940,3640,'Relationship');s.push(`<ellipse cx="1290" cy="3630" rx="100" ry="30" class="oval"/>`);label(1420,3640,'Attribute');s.push(`<ellipse cx="1830" cy="3630" rx="110" ry="30" class="ovalPK"/>`);label(1980,3640,'Primary key');label(2900,3640,'Cardinalities: 1 = one   N = many   0..1 = optional one');txt(220,3735,'Attendance is shown as an optional extension because its complete CREATE TABLE definition is not present in db_setup.sql.','note','start');s.push('</svg>');fs.writeFileSync('outputs/examsphere_er_diagram_human_bw.svg',s.join('\n'),'utf8');
