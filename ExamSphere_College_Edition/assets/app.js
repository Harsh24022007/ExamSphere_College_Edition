document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.toast,.portal-toast').forEach(t=>setTimeout(()=>t.remove(),4000));
  document.querySelectorAll('input[type=number]').forEach(i=>i.addEventListener('wheel',e=>e.preventDefault(),{passive:false}));
  const shell=document.querySelector('.app-shell');
  const sidebar=document.getElementById('sidebar');
  const toggle=document.getElementById('sidebar-toggle');
  if(!shell||!sidebar||!toggle)return;

  const desktop=window.matchMedia('(min-width: 801px)');
  const updateToggle=()=>{
    const expanded=desktop.matches?!shell.classList.contains('sidebar-hidden'):sidebar.classList.contains('open');
    const label=desktop.matches
      ?(expanded?'Hide admin navigation':'Show admin navigation')
      :(expanded?'Close admin navigation':'Open admin navigation');
    toggle.setAttribute('aria-expanded',String(expanded));
    toggle.setAttribute('aria-label',label);
    toggle.title=label;
  };
  const setDesktopPreference=hidden=>{
    try{
      window.localStorage.setItem('examsphere-admin-sidebar-hidden',String(hidden));
    }catch(error){
      console.warn('Could not save the admin navigation preference.',error);
    }
  };
  try{
    if(desktop.matches&&window.localStorage.getItem('examsphere-admin-sidebar-hidden')==='true'){
      shell.classList.add('sidebar-hidden');
    }
  }catch(error){
    console.warn('Could not read the admin navigation preference.',error);
  }
  toggle.addEventListener('click',()=>{
    if(desktop.matches){
      const hidden=shell.classList.toggle('sidebar-hidden');
      sidebar.classList.remove('open');
      setDesktopPreference(hidden);
    }else{
      sidebar.classList.toggle('open');
    }
    updateToggle();
  });
  desktop.addEventListener('change',()=>{
    sidebar.classList.remove('open');
    updateToggle();
  });
  updateToggle();
});