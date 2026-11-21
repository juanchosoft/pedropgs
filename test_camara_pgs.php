<?php
// Prueba independiente. No carga sesiones, no accede a MySQL y no sube fotos al servidor.
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><title>PGS Centrum | Prueba de cámara</title>
<style>
*{box-sizing:border-box}body{font:16px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;background:#f3f5f9;color:#142033;margin:0;padding:18px}main{max-width:650px;margin:auto;background:#fff;border:1px solid #dfe4ec;border-radius:20px;padding:20px;box-shadow:0 12px 35px #10182812}h1{font-size:1.4rem;margin:0 0 5px}p{margin:6px 0 15px;color:#516074}label{display:block;font-weight:650;margin-bottom:6px}select{width:100%;font-size:16px;padding:12px;border:1px solid #cbd5e1;border-radius:12px;background:white;margin-bottom:12px}button{border:0;border-radius:12px;padding:13px 16px;min-height:48px;font-weight:750;font-size:15px;cursor:pointer;background:#ca1b34;color:white}button.secondary{background:#e9edf3;color:#182230}button:disabled{opacity:.55;cursor:default}.actions{display:flex;flex-wrap:wrap;gap:9px;margin-bottom:12px}.actions>*{flex:1 1 140px}.frame{position:relative;background:#0b1220;border-radius:16px;min-height:210px;display:grid;place-items:center;overflow:hidden;color:#d0d5dd}video{width:100%;max-height:53vh;display:block;object-fit:contain}video[hidden],img[hidden]{display:none}.frame p{color:#d0d5dd;padding:20px;text-align:center}img#foto{display:block;width:100%;max-height:400px;object-fit:contain;border-radius:14px;margin-top:12px}#foto[hidden]{display:none}.status{background:#f1f5fa;border:1px solid #dfe7f0;border-radius:12px;padding:12px;margin:14px 0;white-space:pre-wrap;font-size:14px}#status.error{background:#fff2f2;border-color:#f6bbbb;color:#a21d2a}small{display:block;color:#64748b;line-height:1.5}a{color:#b21a30}
</style></head><body><main>
<h1>📷 Prueba de cámara — PGS Centrum</h1><p>Esta página prueba el mismo sistema de acceso a cámara que funciona en <code>report.php</code>: <code>navigator.mediaDevices.getUserMedia()</code>. No guarda ni transmite fotografías.</p>
<div class="status" id="status" role="status" aria-live="polite">Comprobando compatibilidad…</div>
<label for="cameras">Cámara disponible</label><select id="cameras" disabled><option value="">Seleccionar automáticamente (trasera)</option></select>
<div class="actions"><button type="button" id="start">Activar cámara</button><button type="button" id="capture" disabled>Capturar foto</button><button type="button" id="stop" class="secondary" disabled>Apagar cámara</button></div>
<div class="frame"><video id="video" autoplay playsinline muted hidden></video><p id="placeholder">La vista en vivo aparecerá aquí cuando actives la cámara.</p></div>
<img id="foto" alt="Fotografía capturada" hidden>
<div class="actions" style="margin-top:12px"><button type="button" id="retry" class="secondary">Volver a detectar cámaras</button></div>
<small>En PC usa la cámara conectada al computador. Para probar desde el celular, abre esta página mediante <strong>HTTPS</strong> (o localhost del propio dispositivo). Entrar por <code>http://192.168.x.x</code> puede impedir el acceso a la cámara aunque el sistema funcione.</small>
</main>
<script>
(()=>{'use strict';
const $=id=>document.getElementById(id);
const video=$('video'),select=$('cameras'),status=$('status'),start=$('start'),capture=$('capture'),stop=$('stop'),photo=$('foto'),placeholder=$('placeholder');
let stream=null,generation=0;
const say=(message,error=false)=>{status.textContent=message;status.classList.toggle('error',error)};
const diagnostics=()=>`Origen: ${location.origin}\nContexto seguro: ${window.isSecureContext?'Sí':'NO'}\nAPI cámara: ${!!navigator.mediaDevices?.getUserMedia?'Disponible':'NO disponible'}`;
const close=()=>{generation++;if(stream){stream.getTracks().forEach(t=>t.stop());stream=null}video.pause();video.srcObject=null;video.hidden=true;placeholder.hidden=false;capture.disabled=true;stop.disabled=true;start.disabled=false};
async function devices(){
 if(!navigator.mediaDevices?.enumerateDevices)return;
 try{const list=await navigator.mediaDevices.enumerateDevices();const cams=list.filter(x=>x.kind==='videoinput');const previous=select.value;select.replaceChildren(new Option('Automática (preferir trasera)',''));
 cams.forEach((c,i)=>select.add(new Option(c.label||`Cámara ${i+1}`,c.deviceId)));
 select.disabled=cams.length===0;if(previous&&cams.some(c=>c.deviceId===previous))select.value=previous;
 return cams.length;
 }catch(e){say(`No se pudieron enumerar cámaras: ${e.name}.\n${diagnostics()}`,true);return 0}
}
async function activate(){
 if(!window.isSecureContext||!navigator.mediaDevices?.getUserMedia){say(`El navegador no permite abrir la cámara en este origen.\n${diagnostics()}\nUsa HTTPS o localhost.`,true);return}
 close();const gen=++generation;start.disabled=true;say('Solicitando permiso de cámara…');
 let desired=select.value?{deviceId:{exact:select.value}}:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}};
 try{let s;
 try{s=await navigator.mediaDevices.getUserMedia({audio:false,video:desired})}
 catch(e){if(select.value&&['OverconstrainedError','NotFoundError'].includes(e.name))s=await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'}}});else throw e}
 if(gen!==generation){s.getTracks().forEach(t=>t.stop());return}
 stream=s;video.srcObject=s;video.hidden=false;placeholder.hidden=true;await video.play();capture.disabled=false;stop.disabled=false;
 const track=s.getVideoTracks()[0];say(`Cámara activa: ${track?.label||'Cámara disponible'}\nResolución: ${video.videoWidth} × ${video.videoHeight}\nYa puedes capturar la fotografía.`);
 await devices();
 }catch(e){if(gen!==generation)return;close();let hint='Comprueba que otra aplicación no esté utilizando la cámara.';
 if(['NotAllowedError','PermissionDeniedError'].includes(e.name))hint='Permite la cámara para este sitio desde los ajustes de permisos del navegador.';
 if(e.name==='NotFoundError')hint='El dispositivo no reporta ninguna cámara disponible.';
 if(e.name==='NotReadableError')hint='La cámara está ocupada o el sistema no puede acceder a ella.';
 say(`Error: ${e.name}\n${e.message||''}\n${hint}\n\n${diagnostics()}`,true)}
}
start.addEventListener('click',activate);stop.addEventListener('click',()=>{close();say('Cámara apagada. Puedes volver a activarla.')});select.addEventListener('change',()=>{if(stream)activate()});
capture.addEventListener('click',()=>{if(!stream||!video.videoWidth){say('La cámara todavía no está lista.',true);return}const canvas=document.createElement('canvas');const scale=Math.min(1,1280/Math.max(video.videoWidth,video.videoHeight));canvas.width=Math.round(video.videoWidth*scale);canvas.height=Math.round(video.videoHeight*scale);canvas.getContext('2d').drawImage(video,0,0,canvas.width,canvas.height);photo.src=canvas.toDataURL('image/jpeg',.7);photo.hidden=false;say('Fotografía capturada correctamente. Esta prueba no envía ni guarda la imagen.');close()});
$('retry').addEventListener('click',async()=>{const n=await devices();say(`${n??0} cámara(s) detectadas.\n${diagnostics()}`)});
document.addEventListener('visibilitychange',()=>{if(document.hidden)close()});window.addEventListener('pagehide',close);
(async()=>{const n=await devices();say(`${n??0} cámara(s) detectadas.\n${diagnostics()}${!window.isSecureContext?'\nSe requiere HTTPS o localhost para activar la cámara.':''}`,!window.isSecureContext||!navigator.mediaDevices?.getUserMedia)})();
})();
</script></body></html>
