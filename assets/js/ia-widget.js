/**
 * PGS Centrum - AI Assistant Widget Client Script
 */
(function () {
  "use strict";

  const CFG = window.PGS_IA_CFG || { voz: false, adjuntos: false, csrf: "", maxFiles: 5, maxBytes: {} };

  let conversacionActual = sessionStorage.getItem("pgs_ia_conv") ? parseInt(sessionStorage.getItem("pgs_ia_conv"), 10) : null;
  let enviando = false;
  let adjuntosPendientes = [];
  let grabador = null;
  let fragmentosAudio = [];
  let grabando = false;

  function $(id) {
    return document.getElementById(id);
  }

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function formatoEnLinea(s) {
    s = s.replace(/`([^`\n]+)`/g, "<code>$1</code>");
    s = s.replace(/\*\*([^*\n]+)\*\*/g, "<strong>$1</strong>");
    s = s.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, "$1<em>$2</em>");
    s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    return s;
  }

  function celdasDeFilaMarkdown(fila) {
    const partes = fila.split("|");
    if (partes.length && partes[0].trim() === "") partes.shift();
    if (partes.length && partes[partes.length - 1].trim() === "") partes.pop();
    return partes.map(c => c.trim());
  }

  function esFilaTabla(linea) {
    return /^\s*\|.*\|\s*$/.test(linea);
  }

  function esSeparadorTabla(linea) {
    return /^\s*\|?[\s:|-]+\|?\s*$/.test(linea) && linea.indexOf("-") !== -1;
  }

  function markdownSeguro(textoOriginal) {
    const lineas = esc(textoOriginal).split("\n");
    const bloques = [];
    let i = 0;

    while (i < lineas.length) {
      const linea = lineas[i];
      if (/^\s*$/.test(linea)) { i++; continue; }

      const encabezado = linea.match(/^\s{0,3}(#{1,4})\s+(.*)$/);
      if (encabezado) {
        bloques.push("<h4>" + formatoEnLinea(encabezado[2]) + "</h4>");
        i++;
        continue;
      }

      if (esFilaTabla(linea) && lineas[i + 1] && esSeparadorTabla(lineas[i + 1])) {
        const encabezados = celdasDeFilaMarkdown(linea);
        let j = i + 2;
        const filas = [];
        while (j < lineas.length && esFilaTabla(lineas[j])) {
          filas.push(celdasDeFilaMarkdown(lineas[j]));
          j++;
        }
        let tabla = "<table><thead><tr>";
        encabezados.forEach(c => { tabla += "<th>" + formatoEnLinea(c) + "</th>"; });
        tabla += "</tr></thead><tbody>";
        filas.forEach(fila => {
          tabla += "<tr>";
          fila.forEach(c => { tabla += "<td>" + formatoEnLinea(c) + "</td>"; });
          tabla += "</tr>";
        });
        tabla += "</tbody></table>";
        bloques.push(tabla);
        i = j;
        continue;
      }

      const esItemLista = /^\s*([-*]|\d+\.)\s+/.test(linea);
      if (esItemLista) {
        const ordenada = /^\s*\d+\.\s+/.test(linea);
        const items = [];
        let j = i;
        while (j < lineas.length && /^\s*([-*]|\d+\.)\s+/.test(lineas[j])) {
          items.push("<li>" + formatoEnLinea(lineas[j].replace(/^\s*([-*]|\d+\.)\s+/, "")) + "</li>");
          j++;
        }
        bloques.push((ordenada ? "<ol>" : "<ul>") + items.join("") + (ordenada ? "</ol>" : "</ul>"));
        i = j;
        continue;
      }

      const parrafo = [];
      let j = i;
      while (
        j < lineas.length &&
        !/^\s*$/.test(lineas[j]) &&
        !/^\s{0,3}#{1,4}\s+/.test(lineas[j]) &&
        !/^\s*([-*]|\d+\.)\s+/.test(lineas[j]) &&
        !esFilaTabla(lineas[j])
      ) {
        parrafo.push(lineas[j]);
        j++;
      }
      bloques.push("<p>" + formatoEnLinea(parrafo.join("<br>")) + "</p>");
      i = j;
    }

    return bloques.join("");
  }

  function agregarMensaje(rol, texto, mensajeId, pendingActions) {
    const cont = $("pgsIaMsgs");
    if (!cont) return;

    const div = document.createElement("div");
    div.className = "pgs-ia-msg " + (rol === "user" ? "pgs-ia-msg-user" : "pgs-ia-msg-bot");

    if (rol === "user") {
      div.innerHTML = esc(texto).replace(/\n/g, "<br>");
    } else {
      div.innerHTML = markdownSeguro(texto);

      // Pending action card if present
      if (pendingActions && pendingActions.length) {
        pendingActions.forEach(act => {
          const card = document.createElement("div");
          card.className = "pgs-ia-action-card";
          card.innerHTML = `
            <div class="pgs-ia-action-title"><i class="fa fa-exclamation-circle"></i> Action Awaiting Confirmation</div>
            <div class="pgs-ia-action-summary">${esc(act.summary)}</div>
            <div class="pgs-ia-action-btns">
              <button type="button" class="pgs-ia-btn-confirm"><i class="fa fa-check"></i> Confirm</button>
              <button type="button" class="pgs-ia-btn-cancel"><i class="fa fa-times"></i> Cancel</button>
            </div>
          `;

          card.querySelector(".pgs-ia-btn-confirm").addEventListener("click", function () {
            card.querySelectorAll("button").forEach(b => b.disabled = true);
            enviarMensaje("Confirm");
          });

          card.querySelector(".pgs-ia-btn-cancel").addEventListener("click", function () {
            card.querySelectorAll("button").forEach(b => b.disabled = true);
            enviarMensaje("Cancel");
          });

          div.appendChild(card);
        });
      }

      // Voice TTS button if available
      if (mensajeId && CFG.voz) {
        const btnTts = document.createElement("button");
        btnTts.type = "button";
        btnTts.className = "pgs-ia-play-btn";
        btnTts.innerHTML = '<i class="fa fa-volume-up"></i> Listen';
        btnTts.addEventListener("click", function () {
          reproducirTTS(mensajeId, btnTts);
        });
        div.appendChild(btnTts);
      }
    }

    cont.appendChild(div);
    cont.scrollTop = cont.scrollHeight;
    return div;
  }

  function reproducirTTS(mensajeId, btnEl) {
    if (btnEl) {
      btnEl.disabled = true;
      btnEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating audio...';
    }

    const xhr = new XMLHttpRequest();
    xhr.open("POST", "admin/ajax/ia_tts.php");
    xhr.setRequestHeader("X-CSRF-Token", CFG.csrf);
    xhr.responseType = "blob";

    xhr.onload = function () {
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerHTML = '<i class="fa fa-volume-up"></i> Listen';
      }
      if (xhr.status === 200) {
        const audioUrl = URL.createObjectURL(xhr.response);
        const player = new Audio(audioUrl);
        player.play();
      }
    };
    xhr.onerror = function () {
      if (btnEl) {
        btnEl.disabled = false;
        btnEl.innerHTML = '<i class="fa fa-volume-up"></i> Listen';
      }
    };

    const fd = new FormData();
    fd.append("mensaje_id", mensajeId);
    xhr.send(fd);
  }

  function enviarMensaje(textoSobrescrito) {
    if (enviando) return;

    const txtArea = $("pgsIaText");
    const texto = textoSobrescrito != null ? textoSobrescrito : (txtArea ? txtArea.value.trim() : "");

    const adjuntoIds = adjuntosPendientes.map(a => a.public_id);
    if (!texto && !adjuntoIds.length) return;

    enviando = true;
    if (txtArea && textoSobrescrito == null) txtArea.value = "";

    agregarMensaje("user", texto);

    // Indicator
    const typing = document.createElement("div");
    typing.className = "pgs-ia-typing";
    typing.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Thinking...';
    $("pgsIaMsgs").appendChild(typing);
    $("pgsIaMsgs").scrollTop = $("pgsIaMsgs").scrollHeight;

    const fd = new FormData();
    fd.append("mensaje", texto);
    if (conversacionActual) {
      fd.append("conversacion_id", conversacionActual);
    }
    adjuntoIds.forEach(id => {
      fd.append("adjuntos[]", id);
    });
    fd.append("csrf_token", CFG.csrf);

    // Clear chips
    adjuntosPendientes = [];
    renderChips();

    fetch("admin/ajax/ia_chat.php", {
      method: "POST",
      headers: { "X-CSRF-Token": CFG.csrf },
      body: fd,
    })
      .then(res => res.json())
      .then(data => {
        typing.remove();
        enviando = false;

        if (data && data.output && data.output.valid) {
          const resp = data.output.response;
          conversacionActual = resp.conversacion_id;
          sessionStorage.setItem("pgs_ia_conv", String(conversacionActual));
          agregarMensaje("bot", resp.respuesta, resp.mensaje_id, resp.pending_actions);
        } else {
          const errMsg = (data && data.output && data.output.message) || "An error occurred while generating response.";
          agregarMensaje("bot", "⚠️ " + errMsg);
        }
      })
      .catch(err => {
        typing.remove();
        enviando = false;
        agregarMensaje("bot", "⚠️ Network error: " + err.message);
      });
  }

  function subirArchivo(file) {
    if (adjuntosPendientes.length >= CFG.maxFiles) {
      alert("Maximum " + CFG.maxFiles + " attachments per message.");
      return;
    }

    const chipEl = document.createElement("div");
    chipEl.className = "pgs-ia-chip";
    chipEl.innerHTML = `
      <i class="fa fa-spinner fa-spin"></i>
      <span class="pgs-ia-chip-name">${esc(file.name)}</span>
    `;
    $("pgsIaChips").appendChild(chipEl);

    const fd = new FormData();
    fd.append("file", file);
    fd.append("csrf_token", CFG.csrf);

    fetch("admin/ajax/ia_upload.php", {
      method: "POST",
      headers: { "X-CSRF-Token": CFG.csrf },
      body: fd,
    })
      .then(res => res.json())
      .then(data => {
        if (data && data.output && data.output.valid) {
          const item = data.output.response;
          adjuntosPendientes.push(item);
          renderChips();
        } else {
          chipEl.remove();
          alert((data && data.output && data.output.message) || "Upload failed.");
        }
      })
      .catch(err => {
        chipEl.remove();
        alert("Upload error: " + err.message);
      });
  }

  function renderChips() {
    const cont = $("pgsIaChips");
    if (!cont) return;
    cont.innerHTML = "";

    adjuntosPendientes.forEach((a, idx) => {
      const chip = document.createElement("div");
      chip.className = "pgs-ia-chip";
      const icon = a.kind === "image" ? "fa-file-image-o" : (a.kind === "pdf" ? "fa-file-pdf-o" : (a.kind === "docx" ? "fa-file-word-o" : "fa-file-text-o"));
      chip.innerHTML = `
        <i class="fa ${icon}"></i>
        <span class="pgs-ia-chip-name" title="${esc(a.name)}">${esc(a.name)}</span>
        <button type="button" class="pgs-ia-chip-del" title="Remove">&times;</button>
      `;
      chip.querySelector(".pgs-ia-chip-del").addEventListener("click", () => {
        adjuntosPendientes.splice(idx, 1);
        renderChips();
      });
      cont.appendChild(chip);
    });
  }

  function cargarHistorial() {
    const drawer = $("pgsIaHistDrawer");
    const list = $("pgsIaHistList");
    if (!drawer || !list) return;

    list.innerHTML = '<div style="padding:4px;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Loading...</div>';

    fetch("admin/ajax/ia_historial.php?accion=listar")
      .then(r => r.json())
      .then(data => {
        list.innerHTML = "";
        const convs = (data && data.output && data.output.response && data.output.response.conversaciones) || [];
        if (!convs.length) {
          list.innerHTML = '<div style="padding:4px;color:#94a3b8">No previous conversations.</div>';
          return;
        }

        convs.forEach(c => {
          const item = document.createElement("div");
          item.className = "pgs-ia-hist-item" + (c.id === conversacionActual ? " active" : "");
          item.innerHTML = `
            <span class="pgs-ia-hist-title">${esc(c.titulo || "Conversation #" + c.id)}</span>
            <button type="button" class="pgs-ia-hist-del" title="Delete"><i class="fa fa-trash"></i></button>
          `;

          item.querySelector(".pgs-ia-hist-title").addEventListener("click", () => {
            cargarConversacion(c.id);
            drawer.style.display = "none";
          });

          item.querySelector(".pgs-ia-hist-del").addEventListener("click", (e) => {
            e.stopPropagation();
            if (confirm("Delete this conversation?")) {
              const fd = new FormData();
              fd.append("accion", "borrar");
              fd.append("conversacion_id", c.id);
              fd.append("csrf_token", CFG.csrf);

              fetch("admin/ajax/ia_historial.php", {
                method: "POST",
                headers: { "X-CSRF-Token": CFG.csrf },
                body: fd,
              }).then(() => {
                if (conversacionActual === c.id) {
                  conversacionActual = null;
                  sessionStorage.removeItem("pgs_ia_conv");
                  $("pgsIaMsgs").innerHTML = "";
                }
                cargarHistorial();
              });
            }
          });

          list.appendChild(item);
        });
      });
  }

  function cargarConversacion(id) {
    conversacionActual = id;
    sessionStorage.setItem("pgs_ia_conv", String(id));
    $("pgsIaMsgs").innerHTML = '<div style="padding:8px;color:#94a3b8"><i class="fa fa-spinner fa-spin"></i> Loading conversation...</div>';

    fetch("admin/ajax/ia_historial.php?accion=cargar&conversacion_id=" + id)
      .then(r => r.json())
      .then(data => {
        $("pgsIaMsgs").innerHTML = "";
        if (data && data.output && data.output.valid) {
          const msgs = data.output.response.messages || [];
          const pending = data.output.response.pending_actions || [];
          msgs.forEach((m, idx) => {
            const isLast = idx === msgs.length - 1;
            agregarMensaje(m.rol, m.contenido, m.id, isLast ? pending : null);
          });
        }
      });
  }

  function iniciarGrabacionVoz() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert("Microphone access is not supported by your browser.");
      return;
    }

    navigator.mediaDevices.getUserMedia({ audio: true })
      .then(stream => {
        grabador = new MediaRecorder(stream);
        fragmentosAudio = [];
        grabando = true;
        const mic = $("pgsIaMic");
        if (mic) mic.classList.add("recording");

        grabador.ondataavailable = e => {
          if (e.data.size > 0) fragmentosAudio.push(e.data);
        };

        grabador.onstop = () => {
          grabando = false;
          if (mic) mic.classList.remove("recording");
          stream.getTracks().forEach(t => t.stop());

          const blob = new Blob(fragmentosAudio, { type: "audio/webm" });
          enviarAudio(blob);
        };

        grabador.start();
      })
      .catch(err => {
        alert("Microphone permission denied: " + err.message);
      });
  }

  function detenerGrabacionVoz() {
    if (grabador && grabando) {
      grabador.stop();
    }
  }

  function enviarAudio(blob) {
    enviando = true;
    const typing = document.createElement("div");
    typing.className = "pgs-ia-typing";
    typing.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Transcribing voice & processing...';
    $("pgsIaMsgs").appendChild(typing);
    $("pgsIaMsgs").scrollTop = $("pgsIaMsgs").scrollHeight;

    const fd = new FormData();
    fd.append("audio", blob, "voice.webm");
    if (conversacionActual) fd.append("conversacion_id", conversacionActual);
    fd.append("csrf_token", CFG.csrf);

    fetch("admin/ajax/ia_stt.php", {
      method: "POST",
      headers: { "X-CSRF-Token": CFG.csrf },
      body: fd,
    })
      .then(r => r.json())
      .then(data => {
        typing.remove();
        enviando = false;
        if (data && data.output && data.output.valid) {
          const resp = data.output.response;
          conversacionActual = resp.conversacion_id;
          sessionStorage.setItem("pgs_ia_conv", String(conversacionActual));
          agregarMensaje("user", resp.transcripcion || "[Voice Message]");
          agregarMensaje("bot", resp.respuesta, resp.mensaje_id, resp.pending_actions);
        } else {
          agregarMensaje("bot", "⚠️ " + ((data && data.output && data.output.message) || "Voice processing failed."));
        }
      })
      .catch(err => {
        typing.remove();
        enviando = false;
        agregarMensaje("bot", "⚠️ Voice network error: " + err.message);
      });
  }

  // Bind UI Events
  document.addEventListener("DOMContentLoaded", function () {
    const btn = $("pgsIaBtn");
    const panel = $("pgsIaPanel");
    const closeBtn = $("pgsIaCloseBtn");
    const newBtn = $("pgsIaNewBtn");
    const histBtn = $("pgsIaHistBtn");
    const sendBtn = $("pgsIaSend");
    const txtArea = $("pgsIaText");
    const attachBtn = $("pgsIaAttach");
    const fileInput = $("pgsIaFileInput");
    const micBtn = $("pgsIaMic");
    const dropOverlay = $("pgsIaDrop");

    if (!btn || !panel) return;

    btn.addEventListener("click", () => {
      const isHidden = panel.style.display === "none";
      panel.style.display = isHidden ? "flex" : "none";
      if (isHidden && conversacionActual && !$("pgsIaMsgs").children.length) {
        cargarConversacion(conversacionActual);
      }
    });

    if (closeBtn) closeBtn.addEventListener("click", () => panel.style.display = "none");

    if (newBtn) {
      newBtn.addEventListener("click", () => {
        conversacionActual = null;
        sessionStorage.removeItem("pgs_ia_conv");
        $("pgsIaMsgs").innerHTML = "";
        adjuntosPendientes = [];
        renderChips();
        if ($("pgsIaHistDrawer")) $("pgsIaHistDrawer").style.display = "none";
      });
    }

    if (histBtn) {
      histBtn.addEventListener("click", () => {
        const drawer = $("pgsIaHistDrawer");
        if (!drawer) return;
        const isHidden = drawer.style.display === "none";
        drawer.style.display = isHidden ? "block" : "none";
        if (isHidden) cargarHistorial();
      });
    }

    if (sendBtn) sendBtn.addEventListener("click", () => enviarMensaje());

    if (txtArea) {
      txtArea.addEventListener("keydown", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          enviarMensaje();
        }
      });

      // Paste images
      txtArea.addEventListener("paste", (e) => {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (let i = 0; i < items.length; i++) {
          if (items[i].type.indexOf("image") !== -1) {
            const blob = items[i].getAsFile();
            subirArchivo(blob);
          }
        }
      });
    }

    if (attachBtn && fileInput) {
      attachBtn.addEventListener("click", () => fileInput.click());
      fileInput.addEventListener("change", () => {
        for (let i = 0; i < fileInput.files.length; i++) {
          subirArchivo(fileInput.files[i]);
        }
        fileInput.value = "";
      });
    }

    if (micBtn) {
      micBtn.addEventListener("click", () => {
        if (!grabando) {
          iniciarGrabacionVoz();
        } else {
          detenerGrabacionVoz();
        }
      });
    }

    // Drag and drop
    if (dropOverlay) {
      panel.addEventListener("dragenter", (e) => {
        e.preventDefault();
        dropOverlay.style.display = "flex";
      });

      dropOverlay.addEventListener("dragover", (e) => e.preventDefault());
      dropOverlay.addEventListener("dragleave", (e) => {
        e.preventDefault();
        dropOverlay.style.display = "none";
      });

      dropOverlay.addEventListener("drop", (e) => {
        e.preventDefault();
        dropOverlay.style.display = "none";
        if (e.dataTransfer && e.dataTransfer.files) {
          for (let i = 0; i < e.dataTransfer.files.length; i++) {
            subirArchivo(e.dataTransfer.files[i]);
          }
        }
      });
    }
  });

  // Global window.PGS_IA Hook
  window.PGS_IA = {
    open: function (opts) {
      const panel = $("pgsIaPanel");
      if (panel) panel.style.display = "flex";

      if (opts && opts.prefill && $("pgsIaText")) {
        $("pgsIaText").value = opts.prefill;
      }

      if (opts && opts.attach && $("pgsIaFileInput")) {
        $("pgsIaFileInput").click();
      }
    },
    close: function () {
      const panel = $("pgsIaPanel");
      if (panel) panel.style.display = "none";
    },
    send: function (text) {
      this.open();
      enviarMensaje(text);
    },
  };
})();
