(() => {
 'use strict';
 const b64 = s => Uint8Array.from(atob(s.replace(/-/g,'+').replace(/_/g,'/') + '='.repeat((4-s.length%4)%4)), c => c.charCodeAt(0));
 const enc = b => {let s='';new Uint8Array(b).forEach(x=>s+=String.fromCharCode(x));return btoa(s).replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,'');};
 const show = (id,msg) => { const e=document.getElementById(id);if(e){e.textContent=msg;e.hidden=false;} };
 async function api(action, extras, csrf) {
  const r=await fetch('admin/api/passkeys.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,csrf,...extras})});
  const data=await r.json().catch(()=>({error:'Server did not return JSON'}));
  if(!r.ok || !data.ok)throw new Error(data.error||'Passkey operation failed');
  return data;
 }
 const csrf = () => document.querySelector('input[name=login_csrf]')?.value || document.querySelector('[data-passkey-setup]')?.dataset.csrf;
 const supportReason = () => {
  if (!window.isSecureContext) return 'This page is not a secure context. Open the HTTPS address directly in Chrome or Safari.';
  if (!window.PublicKeyCredential || !navigator.credentials || typeof navigator.credentials.create !== 'function' || typeof navigator.credentials.get !== 'function')
    return 'This browser does not support Passkeys. Open PGS Centrum in Chrome (Android) or Safari (iPhone), not an in-app browser.';
  return '';
 };
 const supported = () => !supportReason();
 const login = document.getElementById('pgsPasskeyLogin');
 if(login){login.addEventListener('click',async()=>{
  if(!supported()){show('pgsPasskeyError',supportReason());return;}
  const nickname=document.getElementById('nickname')?.value?.trim();
  if(!nickname){show('pgsPasskeyError','Enter your username first.');return;}
  login.disabled=true;
  try{
   const {publicKey}=await api('login_begin',{nickname},csrf());
   publicKey.challenge=b64(publicKey.challenge);
   publicKey.allowCredentials=publicKey.allowCredentials.map(x=>({...x,id:b64(x.id)}));
   const c=await navigator.credentials.get({publicKey});
   const result=await api('login_finish',{credential:{id:c.id,response:{clientDataJSON:enc(c.response.clientDataJSON),authenticatorData:enc(c.response.authenticatorData),signature:enc(c.response.signature)}}},csrf());
   window.location.assign(result.redirect||'main.php');
  }catch(e){show('pgsPasskeyError',e.name==='NotAllowedError'?'Authentication canceled or device not available.':e.message);login.disabled=false;}
 });}
 const reg=document.getElementById('pgsRegisterPasskey');
 if(reg){
  const msg=document.getElementById('msg');
  reg.addEventListener('click',async()=>{
   if(!supported()){msg.textContent=supportReason();return;}
   reg.disabled=true;msg.textContent='Requesting device verification…';
   try{
    const {publicKey}=await api('register_begin',{},csrf());
    publicKey.challenge=b64(publicKey.challenge);publicKey.user.id=b64(publicKey.user.id);
    publicKey.excludeCredentials=publicKey.excludeCredentials.map(x=>({...x,id:b64(x.id)}));
    const c=await navigator.credentials.create({publicKey});
    await api('register_finish',{credential:{id:c.id,response:{clientDataJSON:enc(c.response.clientDataJSON),attestationObject:enc(c.response.attestationObject)}}},csrf());
    msg.style.color='#9af7c2';msg.textContent='Biometric sign-in enabled!';
    setTimeout(()=>window.location.assign('main.php'),900);
   }catch(e){msg.textContent=e.name==='NotAllowedError'?'Registration canceled or not supported by your device.':e.message;reg.disabled=false;}
  });
  const compat=document.getElementById('pgsCompatibility');
  if(compat){compat.textContent = supportReason() || 'Secure context and WebAuthn API detected. You can enable a Passkey.';}
  api('status',{},csrf()).then(x=>{if(x.registered){msg.style.color='#9af7c2';msg.textContent='You already have a registered Passkey. You can continue to the dashboard.';reg.textContent='Add another Passkey';}}).catch(()=>{});
 }
})();
