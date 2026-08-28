document.addEventListener('DOMContentLoaded', () => {
    const config = window.layoutBuilderConfig;
    const canvas = document.getElementById('layoutCanvas');
    const list = document.getElementById('variableList');
    const properties = document.getElementById('properties');
    const fields = {text: document.getElementById('propText'), x: document.getElementById('propX'), y: document.getElementById('propY'), width: document.getElementById('propWidth'), height: document.getElementById('propHeight'), color: document.getElementById('propColor'), align: document.getElementById('propAlign')};
    const alignments = {esquerda: 'left', direita: 'right', centralizado: 'center', justificado: 'justify'};
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
            color: /^#[0-9a-f]{6}$/i.test(item.color || '') ? item.color : (variable?.color || '#111827'), align: item.align || variable?.align || 'esquerda'
        };
    };
    elements = elements.filter(item => variableById(item.variable_id)).map(normalize);

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
            else { node.classList.add('layout-element-text'); node.textContent = item.text || 'Texto'; node.style.color = item.color; node.style.textAlign = alignments[item.align] || 'left'; }
            node.addEventListener('pointerdown', event => startPointer(event, item, node)); canvas.appendChild(node);
        });
    }
    function applyBox(node, item) { node.style.left = `${item.x / config.width * 100}%`; node.style.top = `${item.y / config.height * 100}%`; node.style.width = `${item.width / config.width * 100}%`; node.style.height = `${item.height / config.height * 100}%`; }
    function select(uid) { selected = uid; const item = elements.find(entry => entry.uid === uid); render(); properties.classList.toggle('d-none', !item); if (!item) return; fields.text.value = item.text || ''; fields.x.value = rounded(item.x); fields.y.value = rounded(item.y); fields.width.value = rounded(item.width); fields.height.value = rounded(item.height); fields.color.value = item.color || '#111827'; fields.align.value = item.align || 'esquerda'; document.getElementById('textProperty').classList.toggle('d-none', item.type !== 'texto'); document.getElementById('textStyles').classList.toggle('d-none', item.type !== 'texto'); }
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
    document.getElementById('removeElement').addEventListener('click', () => { elements = elements.filter(item => item.uid !== selected); selected = null; properties.classList.add('d-none'); render(); });
    canvas.addEventListener('pointerdown', event => { if (event.target === canvas) select(null); });
    const serializeElements = () => JSON.stringify(elements.map(({uid, variable_id, type, text, x, y, width, height, color, align}) => ({uid, variable_id, type, text, x: rounded(x), y: rounded(y), width: rounded(width), height: rounded(height), color, align})));
    document.getElementById('builderForm').addEventListener('submit', () => { document.getElementById('layoutJson').value = serializeElements(); });
    document.getElementById('previewPdf').addEventListener('click', () => {
        const previewForm = document.createElement('form'); previewForm.method = 'POST'; previewForm.action = config.previewUrl; previewForm.target = '_blank'; previewForm.style.display = 'none';
        const token = document.querySelector('#builderForm input[name="_token"]').cloneNode();
        const payload = document.createElement('input'); payload.type = 'hidden'; payload.name = 'layout_json'; payload.value = serializeElements();
        previewForm.append(token, payload); document.body.appendChild(previewForm); previewForm.submit(); previewForm.remove();
    });
    render();
});
