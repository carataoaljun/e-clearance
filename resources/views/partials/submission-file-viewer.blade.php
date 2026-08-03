@once
<div class="submission-preview-overlay" id="submissionFileViewer" role="dialog" aria-modal="true" aria-labelledby="submissionFileViewerTitle" aria-hidden="true" hidden>
    <button class="submission-preview-backdrop" type="button" data-file-preview-close aria-label="Close file preview"></button>
    <section class="submission-preview-panel" role="document">
        <header class="submission-preview-header">
            <div class="submission-preview-heading">
                <span class="submission-preview-icon"><i class="bi bi-file-earmark-text"></i></span>
                <div><h2 id="submissionFileViewerTitle">Student Submission</h2><p id="submissionFileViewerName">Previewing submitted file</p></div>
            </div>
            <button class="submission-preview-close" type="button" data-file-preview-close aria-label="Close file preview"><i class="bi bi-x-lg"></i><span>Close</span></button>
        </header>
        <div class="submission-preview-body">
            <div class="submission-preview-loading" id="submissionFileViewerLoading"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Loading file preview…</span></div>
            <div class="submission-preview-image-stage" id="submissionFileViewerImageStage" hidden>
                <img id="submissionFileViewerImage" src="" alt="Student submission image preview">
            </div>
            <div class="submission-preview-zoom" id="submissionFileViewerZoom" aria-label="Image zoom controls" hidden>
                <button type="button" data-image-zoom="out" aria-label="Zoom out"><i class="bi bi-dash-lg"></i></button>
                <button class="submission-preview-zoom-level" type="button" data-image-zoom="reset" aria-label="Reset zoom"><span id="submissionFileViewerZoomLevel">100%</span></button>
                <button type="button" data-image-zoom="in" aria-label="Zoom in"><i class="bi bi-plus-lg"></i></button>
            </div>
            <iframe id="submissionFileViewerFrame" title="Student submission file preview" src="about:blank"></iframe>
        </div>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const viewer = document.getElementById('submissionFileViewer');
    if (!viewer) return;
    const frame = document.getElementById('submissionFileViewerFrame');
    const image = document.getElementById('submissionFileViewerImage');
    const imageStage = document.getElementById('submissionFileViewerImageStage');
    const zoomControls = document.getElementById('submissionFileViewerZoom');
    const zoomLevel = document.getElementById('submissionFileViewerZoomLevel');
    const previewBody = viewer.querySelector('.submission-preview-body');
    const fileName = document.getElementById('submissionFileViewerName');
    const loading = document.getElementById('submissionFileViewerLoading');
    const closeButton = viewer.querySelector('.submission-preview-close');
    let activeTrigger = null;
    let closeTimer = null;
    let imageZoom = 1;
    let fittedImageWidth = 0;
    let fittedImageHeight = 0;

    const applyImageZoom = () => {
        if (!fittedImageWidth || !fittedImageHeight) return;
        image.style.width = `${Math.round(fittedImageWidth * imageZoom)}px`;
        image.style.height = `${Math.round(fittedImageHeight * imageZoom)}px`;
        zoomLevel.textContent = `${Math.round(imageZoom * 100)}%`;
    };

    const fitImage = () => {
        const horizontalPadding = 36;
        const verticalPadding = 36;
        const availableWidth = Math.max(previewBody.clientWidth - horizontalPadding, 1);
        const availableHeight = Math.max(previewBody.clientHeight - verticalPadding, 1);
        const fitScale = Math.min(availableWidth / image.naturalWidth, availableHeight / image.naturalHeight, 1);
        fittedImageWidth = image.naturalWidth * fitScale;
        fittedImageHeight = image.naturalHeight * fitScale;
        imageZoom = 1;
        applyImageZoom();
        previewBody.scrollTo({ top: 0, left: 0 });
    };

    const openViewer = (trigger) => {
        window.clearTimeout(closeTimer);
        activeTrigger = trigger;
        fileName.textContent = trigger.dataset.fileName || 'Previewing submitted file';
        loading.hidden = false;
        const extension = (trigger.dataset.fileName || '').split('.').pop().toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
        previewBody.classList.toggle('is-image', isImage);
        imageStage.hidden = !isImage;
        zoomControls.hidden = !isImage;
        frame.hidden = isImage;
        viewer.hidden = false;
        viewer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('submission-preview-open');
        if (isImage) {
            frame.src = 'about:blank';
            image.alt = `${trigger.dataset.fileName || 'Student submission'} preview`;
            image.src = trigger.href;
        } else {
            image.src = '';
            frame.src = trigger.href;
        }
        window.requestAnimationFrame(() => viewer.classList.add('is-open'));
        closeButton.focus();
    };

    const closeViewer = () => {
        if (viewer.hidden) return;
        viewer.classList.remove('is-open');
        viewer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('submission-preview-open');
        closeTimer = window.setTimeout(() => {
            viewer.hidden = true;
            frame.src = 'about:blank';
            image.src = '';
            imageStage.hidden = true;
            zoomControls.hidden = true;
            loading.hidden = false;
        }, 180);
        activeTrigger?.focus();
    };

    document.querySelectorAll('[data-file-preview]').forEach((trigger) => trigger.addEventListener('click', (event) => {
        event.preventDefault();
        openViewer(trigger);
    }));
    viewer.querySelectorAll('[data-file-preview-close]').forEach((control) => control.addEventListener('click', closeViewer));
    frame.addEventListener('load', () => { if (frame.src !== 'about:blank') loading.hidden = true; });
    image.addEventListener('load', () => {
        fitImage();
        loading.hidden = true;
    });
    zoomControls.querySelectorAll('[data-image-zoom]').forEach((control) => control.addEventListener('click', () => {
        const action = control.dataset.imageZoom;
        if (action === 'in') imageZoom = Math.min(3, imageZoom + .25);
        if (action === 'out') imageZoom = Math.max(.5, imageZoom - .25);
        if (action === 'reset') imageZoom = 1;
        applyImageZoom();
    }));
    image.addEventListener('dblclick', () => {
        imageZoom = imageZoom === 1 ? 2 : 1;
        applyImageZoom();
    });
    window.addEventListener('resize', () => { if (!viewer.hidden && !imageStage.hidden && image.complete) fitImage(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !viewer.hidden) closeViewer(); });
})();
</script>
@endpush
@endonce
