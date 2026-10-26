let x = 0, y = 0, zoom = 1, panning = false;
const screen = document.querySelector('.screen');
const update = () => screen.style.transform = `translate( ${x}px, ${y}px) scale( ${zoom})`;

addEventListener('wheel', e => {
    e.preventDefault();
    const newzoom = Math.min(3, Math.max(0.2, zoom * (e.deltaY < 0 ? 1.1 : 0.9)));
    x = e.clientX - (e.clientX - x) * newzoom / zoom;
    y = e.clientY - (e.clientY - y) * newzoom / zoom;
    zoom = newzoom;
    update();
}, { passive:false });

addEventListener("mousedown", e => {
    panning = !e.target.closest('.draggable, .wdheader, .offcanvas');
});
addEventListener("mousemove", e => {
    if (!panning) return;
    x += e.movementX;
    y += e.movementY;
    update();
});
addEventListener("mouseup", () => panning = false);



document.querySelectorAll('.draggable').forEach(card => {
    let isdragging = false;

    card.addEventListener('mousedown', e => {
        if (e.target.closest('.cardtext, .deleteform, .imagecontent, .resizer')) return;
        isdragging = true;
        card.style.cursor = 'grabbing';
        card.style.transform = 'scale(1.05)';
    });

    document.addEventListener('mousemove', (e) => {
        if (!isdragging) return;
        card.style.left = `${parseFloat(card.style.left) + e.movementX / zoom}px`;
        card.style.top  = `${parseFloat(card.style.top)  + e.movementY / zoom}px`;
    });

    document.addEventListener('mouseup', () => {
        if (!isdragging) return;
        isdragging = false;
        card.style.cursor = 'grab';
        card.style.transform = 'scale(1.0)';
        savePosition(card.dataset.type, card.dataset.id, parseInt(card.style.left), parseInt(card.style.top));
    });

    const handle = card.querySelector('.resizer');
    let isresizing = false, w = 0, h = 0;

    handle.addEventListener('mousedown', e => {
       e.preventDefault();
       isresizing = true
        w = card.offsetWidth
        h = card.offsetHeight
    });

    document.addEventListener('mousemove', e => {
       if (!isresizing) return;
       w = Math.max(100, w + e.movementX / zoom);
       h = Math.max(60, h + e.movementY / zoom);
       card.style.width = `${w}px`;
       if (card.dataset.type === 'card') {
           card.style.minHeight = `${h}px`
           h = Math.max(h, card.offsetHeight);
       } else {
           card.style.height = `${h}px`;
       }
    });

    document.addEventListener('mouseup', e => {
       if (!isresizing) return;
       isresizing = false;
       saveSize(card.dataset.type, card.dataset.id, Math.round(w), Math.round(h));
    });
});

function savePosition(type, id, x, y) {
    const body = new URLSearchParams({ update_position: 1, projectid: PROJECT_ID, x, y });
    body.set(type === 'card' ? 'cardid' : 'imageid', id);
    fetch('home.php', { method: 'POST', body });
}

function saveSize(type, id, w, h) {
    const body = new URLSearchParams({ update_size: 1, projectid: PROJECT_ID, w, h});
    body.set(type === 'card' ? 'cardid' : 'imageid', id);
    fetch('home.php', { method: 'POST', body });
}

document.querySelectorAll('.cardtext').forEach(area => {
    area.addEventListener('change', () => {
        const form = area.closest('form');
        fetch('home.php', {
            method: 'POST',
            body: new FormData(form)
        });
    });
});

document.querySelectorAll('.imagecontent').forEach(btn => {
    const form = btn.closest('form');
    const input = form.querySelector('input[type="file"]');
    btn.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        if (input.files.length) form.submit();
    });
});