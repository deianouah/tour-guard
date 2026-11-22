// Minimal equirectangular 360 viewer (WebGL). Replace it with Pannellum / Marzipano / Three.js
// in a real project; the protection lives on the server and does not depend on this file.
(() => {
  'use strict';
  const T = window.TOUR;
  const canvas = document.getElementById('c');
  const bar = document.getElementById('bar');
  const note = document.getElementById('note');
  const gl = canvas.getContext('webgl', { antialias: false });
  if (!gl) { note.textContent = 'WebGL is not available in this browser.'; return; }

  const VS = 'attribute vec2 p; void main(){ gl_Position = vec4(p, 0.0, 1.0); }';
  const FS = [
    'precision mediump float;',
    'uniform vec2 res; uniform float yaw; uniform float pitch; uniform float fov; uniform sampler2D tex;',
    'void main(){',
    '  vec2 uv = (gl_FragCoord.xy - 0.5 * res) / res.y;',
    '  vec3 d = normalize(vec3(uv.x, uv.y, 0.5 / tan(fov * 0.5)));',
    '  float cp = cos(pitch), sp = sin(pitch);',
    '  d = vec3(d.x, d.y * cp + d.z * sp, -d.y * sp + d.z * cp);',
    '  float cy = cos(yaw), sy = sin(yaw);',
    '  d = vec3(d.x * cy + d.z * sy, d.y, -d.x * sy + d.z * cy);',
    '  float lon = atan(d.x, d.z);',
    '  float lat = asin(clamp(d.y, -1.0, 1.0));',
    '  gl_FragColor = texture2D(tex, vec2(lon / 6.2831853 + 0.5, 0.5 - lat / 3.1415926));',
    '}'
  ].join('\n');

  function shader(type, src) {
    const s = gl.createShader(type);
    gl.shaderSource(s, src); gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s));
    return s;
  }
  const prog = gl.createProgram();
  gl.attachShader(prog, shader(gl.VERTEX_SHADER, VS));
  gl.attachShader(prog, shader(gl.FRAGMENT_SHADER, FS));
  gl.linkProgram(prog); gl.useProgram(prog);

  gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
  const pLoc = gl.getAttribLocation(prog, 'p');
  gl.enableVertexAttribArray(pLoc);
  gl.vertexAttribPointer(pLoc, 2, gl.FLOAT, false, 0, 0);

  const U = {};
  ['res', 'yaw', 'pitch', 'fov', 'tex'].forEach((n) => { U[n] = gl.getUniformLocation(prog, n); });
  const tex = gl.createTexture();
  gl.bindTexture(gl.TEXTURE_2D, tex);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
  gl.uniform1i(U.tex, 0);

  let yaw = 0, pitch = 0, fov = 1.2, dirty = true, loaded = false;
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

  function resize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.max(1, Math.floor(canvas.clientWidth * dpr));
    canvas.height = Math.max(1, Math.floor(canvas.clientHeight * dpr));
    gl.viewport(0, 0, canvas.width, canvas.height);
    dirty = true;
  }
  window.addEventListener('resize', resize);

  function frame() {
    if (dirty && loaded) {
      gl.uniform2f(U.res, canvas.width, canvas.height);
      gl.uniform1f(U.yaw, yaw); gl.uniform1f(U.pitch, pitch); gl.uniform1f(U.fov, fov);
      gl.drawArrays(gl.TRIANGLES, 0, 3);
      dirty = false;
    }
    requestAnimationFrame(frame);
  }

  let drag = null;
  canvas.addEventListener('pointerdown', (e) => { drag = { x: e.clientX, y: e.clientY }; canvas.setPointerCapture(e.pointerId); });
  canvas.addEventListener('pointerup', () => { drag = null; });
  canvas.addEventListener('pointermove', (e) => {
    if (!drag) return;
    const k = fov / canvas.clientHeight;
    yaw -= (e.clientX - drag.x) * k;
    pitch = clamp(pitch + (e.clientY - drag.y) * k, -1.55, 1.55);
    drag = { x: e.clientX, y: e.clientY };
    dirty = true;
  });
  canvas.addEventListener('wheel', (e) => {
    e.preventDefault();
    fov = clamp(fov + e.deltaY * 0.001, 0.5, 1.9);
    dirty = true;
  }, { passive: false });

  function loadImage(src) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      img.crossOrigin = 'anonymous'; // needed when images come from another origin (S3 mode + bucket CORS)
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('image blocked or link expired'));
      img.src = src;
    });
  }

  async function refreshLinks() {
    const r = await fetch('tour.php?id=' + encodeURIComponent(T.id) + '&json=1', { credentials: 'same-origin', cache: 'no-store' });
    if (!r.ok) throw new Error('could not refresh links (HTTP ' + r.status + ')');
    const d = await r.json();
    T.exp = d.exp;
    d.scenes.forEach((s, i) => { T.scenes[i].src = s.src; });
  }

  async function show(i) {
    note.textContent = 'Loading...';
    try {
      if (Date.now() / 1000 > T.exp - 15) await refreshLinks();
      const img = await loadImage(T.scenes[i].src);
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, img);
      loaded = true; dirty = true;
      [...bar.children].forEach((b, j) => b.classList.toggle('on', j === i));
      note.textContent = 'Drag to look around, scroll to zoom';
    } catch (e) {
      note.textContent = 'Could not load this scene: ' + e.message;
    }
  }

  T.scenes.forEach((s, i) => {
    const b = document.createElement('button');
    b.textContent = s.title;
    b.addEventListener('click', () => show(i));
    bar.appendChild(b);
  });

  resize();
  requestAnimationFrame(frame);
  show(0);
})();
