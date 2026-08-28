document.addEventListener('DOMContentLoaded', () => {
    const config = window.layoutBuilderConfig;
    const canvas = document.getElementById('layoutCanvas');
    const list = document.getElementById('variableList');
    const properties = document.getElementById('properties');
    const fields = {text: document.getElementById('propText'), x: document.getElementById('propX'), y: document.getElementById('propY'), width: document.getElementById('propWidth'), height: document.getElementById('propHeight'), color: document.getElementById('propColor'), align: document.getElementById('propAlign'), fontFamily: document.getElementById('propFontFamily'), fontSize: document.getElementById('propFontSize'), bold: document.getElementById('propBold'), italic: document.getElementById('propItalic'), underline: document.getElementById('propUnderline')};
    const alignments = {esquerda: 'left', direita: 'right', centralizado: 'center', justificado: 'justify'};
    const mainFonts = ['Arial','Helvetica','Times New Roman','Georgia','Courier New','Verdana','Trebuchet MS','Tahoma','Garamond','DejaVu Sans'];
    let elements = Array.isArray(config.elements) ? config.elements : [];
    let selected = null;

    const clamp = (value, min, max) => Math.min(Math.max(Number(value) || 0, min), max);
    const variableById = id => config.variables.find(variable => Number(variable.id) === Number(id));
    const normalize = item => {
        const variable = variableById(item.variable_id);
        return {
            uid: item.uid || `${Date.now()}-${Math.random().toString(16).slice(2)}`,
            variable_id: Number(item.variable_id), type: item.type || variable?.type || 'texto',
            text: item.text ?? variable?.text ?? '', image: variable?.image || item.image || null,
            x: clamp(item.x ?? variable?.x ?? 0, 0, config.width - 1), y: clamp(item.y ?? variable?.y ?? 0, 0, config.height - 1),
            width: clamp(item.width ?? variable?.width ?? 40, 1, config.width), height: clamp(item.height ?? variable?.height ?? 12, 1, config.height),
            color: /^#[0-9a-f]{6}$/i.test(item.color || '') ? item.color : (variable?.color || '#111827'), align: item.align || variable?.align || 'esquerda',
            font_family: item.font_family || 'Arial', font_size: clamp(item.font_size || 12, 1, 300), bold: Boolean(item.bold), italic: Boolean(item.italic), underline: Boolean(item.underline)
        };
    };
    elements = elements.filter(item => variableById(item.variable_id)).map(normalize);

    const addFontOption = (name, url = null) => {
        if (![...fields.fontFamily.options].some(option => option.value === name)) fields.fontFamily.add(new Option(name, name));
        if (url && 'FontFace' in window) new FontFace(name, `url(${JSON.stringify(url)})`).load().then(font => document.fonts.add(font)).catch(() => {});
    };
    mainFonts.forEach(name => addFontOption(name)); (config.fonts || []).forEach(font => addFontOption(font.name, font.url));

    config.variables.forEach(variable => {
        const button = document.createElement('button'); button.type = 'button'; button.className = 'variable-button';
        const icon = variable.type === 'imagem' && variable.image ? `<img class="variable-thumb" src="${variable.image}" alt="">` : `<span class="context-icon"><i class="bi ${variable.type === 'imagem' ? 'bi-image' : 'bi-fonts'}"></i></span>`;
        button.innerHTML = `${icon}<span class="text-truncate"><strong class="d-block small">${variable.type === 'imagem' ? 'Imagem' : 'Texto'} #${variable.id}</strong><span class="small text-muted">${escapeHtml(variable.label)}</span></span>`;
        button.addEventListener('click', () => { const item = normalize({...variable, variable_id: variable.id, uid: ''}); elements.push(item); render(); select(item.uid); }); list.appendChild(button);
    });
    if (!config.variables.length) list.innerHTML = '<div class="alert alert-light small">Nenhuma variável ativa disponível.</div>';

    function escapeHtml(value) { const span = document.createElement('span'); span.textContent = value || ''; return span.innerHTML; }
    function render() {
        canvas.querySelectorAll('.layout-element').forEach(node => node.remove());
        elements.forEach(item => {
            const node = document.createElement('div'); node.className = `layout-element ${item.type === 'imagem' ? 'image' : 'text'}${selected === item.uid ? ' selected' : ''}`; node.dataset.uid = item.uid;
            applyBox(node, item);
            if (item.type === 'imagem') node.innerHTML = item.image ? `<img src="${item.image}" alt="Variável de imagem"><span class="resize-handle"></span>` : '<span class="small text-danger">Imagem indisponível</span><span class="resize-handle"></span>';
            else { node.classList.add('layout-element-text'); node.textContent = item.text || 'Texto'; node.style.color = item.color; node.style.textAlign = alignments[item.align] || 'left'; node.style.fontFamily = item.font_family; node.style.fontSize = `${item.font_size}pt`; node.style.fontWeight = item.bold ? 'bold' : 'normal'; node.style.fontStyle = item.italic ? 'italic' : 'normal'; node.style.textDecoration = item.underline ? 'underline' : 'none'; const handle = document.createElement('span'); handle.className = 'resize-handle'; node.appendChild(handle); }
            node.addEventListener('pointerdown', event => startPointer(event, item, node)); canvas.appendChild(node);
        });
    }
    function applyBox(node, item) { node.style.left = `${item.x / config.width * 100}%`; node.style.top = `${item.y / config.height * 100}%`; node.style.width = `${item.width / config.width * 100}%`; node.style.height = `${item.height / config.height * 100}%`; }
    function select(uid) { selected = uid; const item = elements.find(entry => entry.uid === uid); render(); properties.classList.toggle('d-none', !item); if (!item) return; fields.text.value = item.text || ''; fields.x.value = rounded(item.x); fields.y.value = rounded(item.y); fields.width.value = rounded(item.width); fields.height.value = rounded(item.height); fields.color.value = item.color || '#111827'; fields.align.value = item.align || 'esquerda'; fields.fontFamily.value = item.font_family || 'Arial'; fields.fontSize.value = item.font_size || 12; fields.bold.checked = item.bold; fields.italic.checked = item.italic; fields.underline.checked = item.underline; document.getElementById('textProperty').classList.toggle('d-none', item.type !== 'texto'); document.getElementById('textStyles').classList.toggle('d-none', item.type !== 'texto'); }
    const rounded = value => Math.round(value * 10) / 10;
    function startPointer(event, item, node) {
        event.preventDefault(); const resizing = event.target.classList.contains('resize-handle'); select(item.uid); const activeNode = canvas.querySelector(`[data-uid="${CSS.escape(item.uid)}"]`); const rect = canvas.getBoundingClientRect(); const start = {x: event.clientX, y: event.clientY, left: item.x, top: item.y, width: item.width, height: item.height};
        const move = moveEvent => { const dx = (moveEvent.clientX - start.x) / rect.width * config.width, dy = (moveEvent.clientY - start.y) / rect.height * config.height; if (resizing) { item.width = clamp(start.width + dx, 1, config.width - item.x); item.height = clamp(start.height + dy, 1, config.height - item.y); } else { item.x = clamp(start.left + dx, 0, config.width - item.width); item.y = clamp(start.top + dy, 0, config.height - item.height); } applyBox(activeNode, item); syncControls(item); };
        const stop = () => { document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', stop); };
        document.addEventListener('pointermove', move); document.addEventListener('pointerup', stop);
    }
    function syncControls(item) { fields.x.value = rounded(item.x); fields.y.value = rounded(item.y); fields.width.value = rounded(item.width); fields.height.value = rounded(item.height); }
    ['x','y','width','height'].forEach(key => fields[key].addEventListener('input', () => { const item = elements.find(entry => entry.uid === selected); if (!item) return; const maximum = key === 'x' ? config.width - item.width : key === 'y' ? config.height - item.height : key === 'width' ? config.width - item.x : config.height - item.y; item[key] = clamp(fields[key].value, key === 'width' || key === 'height' ? 1 : 0, maximum); render(); }));
    fields.text.addEventListener('input', () => { const item = elements.find(entry => entry.uid === selected); if (item) { item.text = fields.text.value; render(); } });
    fields.color.addEventListener('input', () => { const item = elements.find(entry => entry.uid === selected); if (item) { item.color = fields.color.value; render(); } });
    fields.align.addEventListener('change', () => { const item = elements.find(entry => entry.uid === selected); if (item) { item.align = fields.align.value; render(); } });
    fields.fontFamily.addEventListener('change', () => { const item = elements.find(entry => entry.uid === selected); if (item) { item.font_family = fields.fontFamily.value; render(); } });
    fields.fontSize.addEventListener('input', () => { const item = elements.find(entry => entry.uid === selected); if (item) { item.font_size = clamp(fields.fontSize.value, 1, 300); render(); } });
    ['bold','italic','underline'].forEach(key => fields[key].addEventListener('change', () => { const item = elements.find(entry => entry.uid === selected); if (item) { item[key] = fields[key].checked; render(); } }));
    const removeSelectedElement = () => { if (!selected) return; elements = elements.filter(item => item.uid !== selected); selected = null; properties.classList.add('d-none'); render(); };
    document.getElementById('removeElement').addEventListener('click', removeSelectedElement);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Delete' || !selected) return;
        const target = event.target;
        if (target instanceof HTMLElement && (target.matches('input, textarea, select') || target.isContentEditable)) return;
        event.preventDefault(); removeSelectedElement();
    });
    canvas.addEventListener('pointerdown', event => { if (event.target === canvas) select(null); });
    const serializeElements = () => JSON.stringify(elements.map(({uid, variable_id, type, text, x, y, width, height, color, align, font_family, font_size, bold, italic, underline}) => ({uid, variable_id, type, text, x: rounded(x), y: rounded(y), width: rounded(width), height: rounded(height), color, align, font_family, font_size, bold, italic, underline})));
    document.getElementById('builderForm').addEventListener('submit', () => { document.getElementById('layoutJson').value = serializeElements(); });
    document.getElementById('previewPdf').addEventListener('click', () => {
        const previewForm = document.createElement('form'); previewForm.method = 'POST'; previewForm.action = config.previewUrl; previewForm.target = '_blank'; previewForm.style.display = 'none';
        const token = document.querySelector('#builderForm input[name="_token"]').cloneNode();
        const payload = document.createElement('input'); payload.type = 'hidden'; payload.name = 'layout_json'; payload.value = serializeElements();
        previewForm.append(token, payload); document.body.appendChild(previewForm); previewForm.submit(); previewForm.remove();
    });
    const fontFile = document.getElementById('fontFile'), fontMessage = document.getElementById('fontMessage');
    document.getElementById('importFont').addEventListener('click', () => fontFile.click());
    fontFile.addEventListener('change', async () => {
        if (!fontFile.files[0]) return; const data = new FormData(); data.append('fonte', fontFile.files[0]);
        fontMessage.className = 'small mt-1 text-muted'; fontMessage.textContent = 'Importando fonte...';
        try { const response = await fetch(config.fontUploadUrl, {method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('#builderForm input[name="_token"]').value,'Accept':'application/json'}, body:data}); const result = await response.json(); if (!response.ok) throw new Error(result.message || Object.values(result.errors || {}).flat()[0] || 'Falha ao importar.'); addFontOption(result.font.name, result.font.url); fields.fontFamily.value = result.font.name; const item = elements.find(entry => entry.uid === selected); if (item?.type === 'texto') { item.font_family = result.font.name; render(); } fontMessage.className = 'small mt-1 text-success'; fontMessage.textContent = `Fonte “${result.font.name}” importada.`; }
        catch (error) { fontMessage.className = 'small mt-1 text-danger'; fontMessage.textContent = error.message; }
        finally { fontFile.value = ''; }
    });
    render();
});
