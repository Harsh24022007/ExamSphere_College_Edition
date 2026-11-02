const fs = require('fs');
const W=3600,H=2400;
let s=[];
const esc=t=>String(t).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
function line(x1,y1,x2,y2,cls='connector'){s.push(`<line class="${cls}" x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"/>`)}
function txt(x,y,t,cls='label',anchor='middle'){s.push(`<text class="${cls}" x="${x}" y="${y}" text-anchor="${anchor}">${esc(t)}</text>`)}
function multiline(x,y,lines,cls='attrText'){lines.forEach((t,i)=>txt(x,y+i*24,t,cls))}
function entity(x,y,w,h,name,sub=''){s.push(`<g><rect class="entity" x="${x}" y="${y}" width="${w}" height="${h}" rx="3"/><text class="entityText" x="${x+w/2}" y="${y+h/2-4}" text-anchor="middle">${esc(name)}</text>${sub?`<text class="entitySub" x="${x+w/2}" y="${y+h/2+25}" text-anchor="middle">${esc(sub)}</text>`:''}</g>`); return {x,y,w,h,cx:x+w/2,cy:y+h/2}}
function diamond(cx,cy,w,h,name){let p=`${cx},${cy-h/2} ${cx+w/2},${cy} ${cx},${cy+h/2} ${cx-w/2},${cy}`;s.push(`<polygon class="relationship" points="${p}"/>`);txt(cx,cy+7,name,'relText');return {cx,cy}}
function attr(cx,cy,rx,ry,lines,pk=false){s.push(`<ellipse class="attribute${pk?' pk':''}" cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}"/>`);multiline(cx,cy-(lines.length-1)*12,lines);return {cx,cy}}
function card(x,y,t){txt(x,y,t,'cardinality')}
function note(x,y,w,h,title,lines){s.push(`<rect class="note" x="${x}" y="${y}" width="${w}" height="${h}" rx="12"/>`);txt(x+18,y+32,title,'noteTitle','start');lines.forEach((t,i)=>txt(x+18,y+62+i*25,t,'noteText','start'))}

s.push(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}" viewBox="0 0 ${W} ${H}">`);
s.push(`<rect width="100%" height="100%" fill="#f8fafc"/>`);
s.push(`<style>
.title{font:700 42px Arial;fill:#0f172a}.subtitle{font:20px Arial;fill:#475569}.entity{fill:#dbeafe;stroke:#1d4ed8;stroke-width:4}.entityText{font:700 24px Arial;fill:#0f172a}.entitySub{font:16px Arial;fill:#1e40af}.relationship{fill:#fef3c7;stroke:#b45309;stroke-width:4}.relText{font:700 20px Arial;fill:#78350f}.attribute{fill:#ffffff;stroke:#475569;stroke-width:3}.attribute.pk{stroke:#dc2626;stroke-width:4}.attrText{font:16px Arial;fill:#111827}.connector{stroke:#334155;stroke-width:3;fill:none}.cardinality{font:700 17px Arial;fill:#dc2626}.note{fill:#f1f5f9;stroke:#64748b;stroke-width:2}.noteTitle{font:700 18px Arial;fill:#0f172a}.noteText{font:15px Arial;fill:#334155}.legendText{font:17px Arial;fill:#334155}.legendHead{font:700 19px Arial;fill:#0f172a}
</style>`);
txt(1800,62,'ExamSphere College Edition — Standard Chen ER Diagram','title');
txt(1800,98,'Fresh-install schema with optional existing-deployment attendance extension','subtitle');

// entities
const dept=entity(180,250,300,90,'DEPARTMENT','strong entity');
const subj=entity(850,250,320,90,'SUBJECT','strong entity');
const exam=entity(1530,250,360,90,'EXAMINATION','strong entity');
const reg=entity(2220,690,420,90,'EXAM_REGISTRATION','associative entity');
const stud=entity(180,980,300,90,'STUDENTS','strong entity');
const rooms=entity(850,1200,300,90,'ROOMS','strong entity');
const alloc=entity(1510,1200,380,90,'ROOM_ALLOCATION','associative entity');
const duty=entity(2240,1200,370,90,'INVIGILATOR_DUTY','associative entity');
const inv=entity(2240,1730,330,90,'INVIGILATOR','strong entity');
const users=entity(1430,1840,280,90,'users','account entity');
const admins=entity(520,1840,330,90,'ADMIN_USERS','auth entity');
const att=entity(2910,1840,300,90,'attendance','optional extension');

// relationships and connectors
const r1=diamond(650,295,210,100,'OFFERS'); line(dept.x+dept.w,dept.cy,r1.cx-r1.w/2, r1.cy); line(r1.cx+r1.w/2,r1.cy,subj.x,subj.cy); card(535,275,'1');card(770,275,'N');
const r2=diamond(650,760,210,100,'HAS'); line(dept.x+dept.w/2,dept.y+dept.h, r2.cx-35,r2.cy-50); line(r2.cx+35,r2.cy+50,stud.x+stud.w/2,stud.y); card(555,735,'1');card(700,800,'N');
const r3=diamond(1350,295,230,100,'SCHEDULES'); line(subj.x+subj.w,subj.cy, r3.cx-r3.w/2,r3.cy); line(r3.cx+r3.w/2,r3.cy,exam.x,exam.cy); card(1215,275,'1');card(1485,275,'N');
const r4=diamond(1330,720,250,100,'REGISTERS'); line(stud.x+stud.w,stud.cy, r4.cx-100,r4.cy); line(r4.cx+100,r4.cy, reg.x,reg.cy); card(540,965,'1');card(2070,685,'N');
const r5=diamond(2010,520,260,100,'ACCEPTS'); line(exam.x+exam.w/2,exam.y+exam.h, r5.cx-120,r5.cy); line(r5.cx+120,r5.cy, reg.x+reg.w/2,reg.y); card(1680,390,'1');card(2100,455,'N');
const r6=diamond(1370,1010,260,100,'ASSIGNED_TO'); line(reg.x+reg.w/2,reg.y+reg.h, r6.cx+110,r6.cy); line(r6.cx-110,r6.cy, alloc.x+alloc.w/2,alloc.y); card(2220,900,'0..1');card(1510,1090,'1');
const r7=diamond(1300,1345,220,100,'USES'); line(rooms.x+rooms.w/2,rooms.y+rooms.h, r7.cx,r7.cy+50); line(r7.cx,r7.cy-50,alloc.x+100,alloc.y+alloc.h); card(1230,1305,'1');card(1415,1305,'N');
const r8=diamond(1990,1010,230,100,'FOR_EXAM'); line(exam.x+exam.w,exam.cy+45, r8.cx-115,r8.cy); line(r8.cx+115,r8.cy, duty.x+70,duty.y); card(1900,350,'1');card(2130,955,'N');
const r9=diamond(2700,1530,260,100,'PERFORMS'); line(inv.x+inv.w/2,inv.y, r9.cx+100,r9.cy+50); line(r9.cx-100,r9.cy-50,duty.x+duty.w/2,duty.y+duty.h); card(2530,1600,'1');card(2550,1320,'N');
const r10=diamond(2930,1010,220,100,'HOSTS'); line(rooms.x+rooms.w,rooms.cy, r10.cx-110,r10.cy); line(r10.cx+110,r10.cy,duty.x,duty.cy); card(1180,1190,'1');card(2820,965,'N');
const r11=diamond(1750,1660,250,100,'ACCOUNT_FOR'); line(stud.x+stud.w,stud.cy+45, r11.cx-125,r11.cy); line(r11.cx+125,r11.cy, users.x,users.cy); card(530,1045,'0..1');card(1800,1790,'0..1');
const r12=diamond(1750,2040,250,100,'ACCOUNT_FOR'); line(inv.x,inv.cy+35, r12.cx+125,r12.cy); line(r12.cx-125,r12.cy, users.x+users.w/2,users.y+users.h); card(2180,1860,'0..1');card(1760,2150,'0..1');
const r13=diamond(2590,1540,250,100,'CHECKS'); line(users.x+users.w,users.cy, r13.cx-125,r13.cy); line(r13.cx+125,r13.cy,att.x+att.w/2,att.y); card(1740,1840,'0..1');card(2780,1780,'N');
const r14=diamond(2620,1430,260,100,'RECORDED_FOR'); line(alloc.x+alloc.w,alloc.cy, r14.cx-130,r14.cy); line(r14.cx+130,r14.cy,att.x+att.w/2,att.y+20); card(1920,1180,'1');card(2780,1380,'N');

// attribute ovals / key facts
attr(105,210,150,42,['DEPT_ID (PK)'],true); line(210,250,180,220);
attr(335,205,155,42,['DEPT_NAME']); line(360,250,335,230);
attr(810,165,150,42,['SUBJECT_ID (PK)'],true); line(900,250,840,205);
attr(1120,165,170,42,['SUBJECT_NAME']); line(1100,250,1120,205);
attr(820,400,150,42,['OFFERED_SEMESTER']); line(950,340,850,380);
attr(1170,400,120,42,['DEPT_ID (FK)']); line(1080,340,1150,380);
attr(1480,165,130,42,['EXAM_ID (PK)'],true); line(1580,250,1540,205);
attr(1770,165,145,42,['EXAM_DATE']); line(1750,250,1770,205);
attr(2010,165,150,42,['START_TIME / END_TIME']); line(1830,250,1990,205);
attr(2140,300,120,42,['published']); line(1850,295,2070,300);
attr(105,935,165,42,['STUDENT_ID (PK)'],true); line(230,980,190,955);
attr(430,935,130,42,['FNAME / MNAME / LNAME']); line(400,980,430,955);
attr(90,1120,155,42,['CURRENT_SEMESTER']); line(220,1070,130,1100);
attr(430,1120,120,42,['DEPT_ID (FK)']); line(390,1070,420,1100);
attr(2130,610,160,42,['REGISTR_ID (PK)'],true); line(2300,690,2220,650);
attr(2580,610,145,42,['STUDENT_ID (FK)']); line(2490,690,2570,650);
attr(2130,820,120,42,['EXAM_ID (FK)']); line(2320,780,2180,800);
attr(2560,820,140,42,['REGISTR_DATE']); line(2520,780,2560,800);
attr(780,1325,145,42,['ROOM_ID (PK)'],true); line(960,1200,850,1340);
attr(1140,1325,155,42,['BUILDING / FLOOR']); line(1050,1290,1130,1330);
attr(780,1480,140,42,['CAPACITY']); line(950,1290,820,1460);
attr(1110,1480,120,42,['STATUS']); line(1050,1290,1100,1460);
attr(1440,1100,180,42,['ALLOCATION_ID (PK)'],true); line(1650,1200,1500,1120);
attr(1870,1100,145,42,['REGISTR_ID (FK)']); line(1780,1200,1850,1120);
attr(1440,1460,130,42,['ROOM_ID (FK)']); line(1650,1290,1490,1450);
attr(1810,1460,120,42,['SEAT_NO']); line(1770,1290,1800,1450);
attr(2160,1100,140,42,['DUTY_ID (PK)'],true); line(2390,1200,2200,1120);
attr(2590,1100,120,42,['EXAM_ID (FK)']); line(2500,1200,2570,1120);
attr(2110,1350,170,42,['INVIGILATOR_ID (FK)']); line(2360,1290,2180,1330);
attr(2560,1350,120,42,['ROOM_ID (FK)']); line(2500,1290,2550,1330);
attr(2370,1880,210,42,['INVIGILATOR_ID (PK)'],true); line(2400,1820,2380,1860);
attr(2680,1880,180,42,['NAME / PHONE / EMAIL']); line(2500,1820,2670,1860);
attr(1350,2150,150,42,['user_id (PK)'],true); line(1550,1930,1450,2130);
attr(1800,2150,180,42,['username / role']); line(1640,1930,1790,2130);
attr(2060,1990,170,42,['student_id / invigilator_id']); line(1710,1900,2020,1980);
attr(400,1960,160,42,['ADMIN_ID (PK)'],true); line(600,1930,440,1950);
attr(820,1960,170,42,['USERNAME / PASSWORD_HASH']); line(770,1930,820,1950);
attr(2800,1990,170,42,['attendance_id (PK)'],true); line(2980,1930,2830,1970);
attr(3270,1990,180,42,['allocation_id / checked_by']); line(3090,1930,3250,1970);

// legend and notes
s.push(`<rect class="note" x="100" y="2220" width="3400" height="120" rx="12"/>`);
s.push(`<rect class="entity" x="135" y="2250" width="90" height="42" rx="2"/>`);txt(250,2278,'Entity / table','legendText','start');
s.push(`<polygon class="relationship" points="520,2271 575,2245 630,2271 575,2297"/>`);txt(650,2278,'Relationship','legendText','start');
s.push(`<ellipse class="attribute" cx="910" cy="2271" rx="70" ry="25"/>`);txt(1010,2278,'Attribute','legendText','start');
s.push(`<ellipse class="attribute pk" cx="1280" cy="2271" rx="80" ry="25"/>`);txt(1390,2278,'Primary key attribute','legendText','start');
txt(1750,2278,'Cardinality labels: 1 = one, N = many, 0..1 = optional one','legendText','start');
txt(1750,2310,'Dashed/optional extension in source: attendance table is referenced by migrations but not defined in db_setup.sql.','legendText','start');
s.push('</svg>');
fs.writeFileSync('outputs/examsphere_er_diagram_standard_chen.svg',s.join('\n'),'utf8');
