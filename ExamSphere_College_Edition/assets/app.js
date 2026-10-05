document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.toast,.portal-toast').forEach(t=>setTimeout(()=>t.remove(),4000));
  document.querySelectorAll('input[type=number]').forEach(i=>i.addEventListener('wheel',e=>e.preventDefault(),{passive:false}));
});