<script setup>
defineProps({
    data: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <div class="print-root" data-print-format="poster">
        <div class="poster" id="posterA4">
            <div class="header-modern">
                <div class="title-section">
                    <h1 id="clinicNameDisplay">{{ data.cabinetName }}</h1>
                    <div id="clinicSubDisplay" class="clinic-sub">{{ data.subtitle }}</div>
                </div>
                <div class="tooth-icon">
                    <i class="pi pi-heart-fill"></i>
                </div>
            </div>

            <div class="asymmetric-layout">
                <div class="left-stack">
                    <div class="qr-card">
                        <div class="card-icon"><i class="pi pi-user"></i></div>
                        <h3>{{ data.entries.portal.title }}</h3>
                        <div class="qr-desc">
                            {{ data.entries.portal.description }}
                        </div>
                        <div class="canvas-wrapper">
                            <img v-if="data.entries.portal.imageSrc" :src="data.entries.portal.imageSrc" :alt="data.entries.portal.title" class="qr-canvas" />
                            <div v-else class="qr-canvas qr-placeholder">URL invalide</div>
                        </div>
                        <div class="badge-qr"><i class="pi pi-qrcode"></i> {{ data.entries.portal.badge }}</div>
                    </div>

                    <div class="qr-card">
                        <div class="card-icon"><i class="pi pi-comments"></i></div>
                        <h3>{{ data.entries.review.title }}</h3>
                        <div class="qr-desc">
                            {{ data.entries.review.description }}
                        </div>
                        <div class="canvas-wrapper">
                            <img v-if="data.entries.review.imageSrc" :src="data.entries.review.imageSrc" :alt="data.entries.review.title" class="qr-canvas" />
                            <div v-else class="qr-canvas qr-placeholder">URL invalide</div>
                        </div>
                        <div class="badge-qr"><i class="pi pi-comment"></i> {{ data.entries.review.badge }}</div>
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-icon"><i class="pi pi-globe"></i></div>
                    <h3>{{ data.entries.showcase.title }}</h3>
                    <div class="feature-desc">
                        {{ data.entries.showcase.description }}
                    </div>
                    <div class="big-canvas-wrapper">
                        <img v-if="data.entries.showcase.imageSrc" :src="data.entries.showcase.imageSrc" :alt="data.entries.showcase.title" class="big-qr-canvas" />
                        <div v-else class="big-qr-canvas qr-placeholder">URL invalide</div>
                    </div>
                    <div class="big-badge"><i class="pi pi-globe"></i> {{ data.entries.showcase.badge }}</div>
                </div>
            </div>

            <div class="footer-note">
                <span><i class="pi pi-phone"></i> {{ data.phone }}</span>
                <span><i class="pi pi-envelope"></i> {{ data.email || 'contact@cabinet.local' }}</span>
                <span><i class="pi pi-calendar"></i> Urgences & Rendez-vous en ligne</span>
                <span><i class="pi pi-shield"></i> Donnees securisees</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.print-root {
    max-width: var(--print-paper-width, 210mm);
    min-height: var(--print-paper-height, 297mm);
    margin-inline: auto;
    padding: 4mm;
    background: #eef5f8;
    font-family: Inter, Arial, sans-serif;
    display: grid;
    overflow-wrap: anywhere;
}

.poster {
    background: #fff;
    border-radius: 40px;
    box-shadow: 0 30px 50px -20px rgba(0, 0, 0, 0.3);
    padding: 8mm;
    position: relative;
}

.poster::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, rgba(44, 155, 143, 0.03) 0%, rgba(255, 255, 255, 0) 70%);
    pointer-events: none;
    border-radius: 50%;
}

.header-modern {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    margin-bottom: 18px;
    border-bottom: 2px solid rgba(44, 155, 143, 0.2);
    padding-bottom: 14px;
}

.title-section h1 {
    font-size: 2.4rem;
    font-weight: 800;
    background: linear-gradient(125deg, #1f6e8c 0%, #2c9b8f 100%);
    background-clip: text;
    -webkit-background-clip: text;
    color: transparent;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.clinic-sub {
    color: #4f7e8c;
    font-weight: 500;
    margin-top: 8px;
    font-size: 0.95rem;
    border-left: 3px solid #2c9b8f;
    padding-left: 14px;
}

.tooth-icon {
    background: #eaf7f5;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 30px;
    font-size: 32px;
    color: #2c9b8f;
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.05);
}

.asymmetric-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
    gap: 24px;
}

.left-stack {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.qr-card {
    background: #fff;
    border-radius: 32px;
    padding: 16px 20px;
    text-align: center;
    transition: all 0.25s ease;
    box-shadow: 0 15px 30px -12px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(44, 155, 143, 0.2);
    backdrop-filter: blur(0px);
}

.card-icon {
    font-size: 2.4rem;
    background: #eef7f5;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin: 0 auto 10px;
    color: #1f6e8c;
}

.qr-card h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f3b43;
    margin-bottom: 12px;
}

.qr-desc {
    font-size: 0.85rem;
    color: #597f8f;
    line-height: 1.45;
    margin: 6px 0 10px;
    padding: 0 8px;
}

.canvas-wrapper,
.big-canvas-wrapper {
    display: flex;
    justify-content: center;
    margin: 12px 0 8px;
}

.qr-canvas {
    width: 140px;
    height: 140px;
    border-radius: 24px;
    background: white;
    padding: 6px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

.badge-qr {
    font-size: 0.7rem;
    background: #f0f6f9;
    display: inline-block;
    padding: 5px 14px;
    border-radius: 40px;
    color: #2c9b8f;
    font-weight: 600;
    margin-top: 8px;
}

.feature-card {
    background: linear-gradient(145deg, #ffffff 0%, #fbfefe 100%);
    border-radius: 42px;
    padding: 36px 28px;
    text-align: center;
    box-shadow: 0 20px 35px -14px rgba(31, 110, 140, 0.2);
    border: 1px solid rgba(44, 155, 143, 0.35);
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.feature-icon {
    font-size: 3.2rem;
    background: linear-gradient(135deg, #1f6e8c, #2c9b8f);
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 60px;
    margin: 0 auto 20px;
    color: white;
    box-shadow: 0 15px 25px -8px rgba(31, 110, 140, 0.4);
}

.feature-card h3 {
    font-size: 1.9rem;
    font-weight: 800;
    color: #1f3b43;
    margin-bottom: 12px;
}

.feature-desc {
    font-size: 1rem;
    color: #4a6e7c;
    max-width: 280px;
    margin: 12px auto 24px;
    line-height: 1.5;
}

.big-qr-canvas {
    width: 200px;
    height: 200px;
    border-radius: 32px;
    background: white;
    padding: 10px;
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
}

.big-badge {
    font-size: 0.8rem;
    font-weight: 600;
    background: #e0f0ed;
    padding: 6px 18px;
    border-radius: 60px;
    color: #1f6e8c;
    display: inline-block;
    margin-top: 18px;
}

.footer-note {
    margin-top: 24px;
    border-top: 2px dashed #cbe0e8;
    padding-top: 14px;
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 0.8rem;
    color: #5c8192;
    font-weight: 500;
}

.footer-note span i {
    margin-right: 8px;
    color: #2c9b8f;
}

.qr-placeholder {
    border: 1px dashed #c8dbe3;
    color: #6d8793;
    text-align: center;
    font-size: 0.75rem;
    display: grid;
    place-items: center;
}

html[data-print-orientation='landscape'] .print-root {
    max-width: var(--print-paper-width, 297mm);
    min-height: var(--print-paper-height, 210mm);
}

html[data-print-orientation='landscape'] .asymmetric-layout {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

html[data-print-orientation='landscape'] .left-stack {
    display: contents;
}

html[data-print-orientation='landscape'] .qr-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

html[data-print-orientation='landscape'] .feature-card {
    padding: 16px 20px;
}

html[data-print-orientation='landscape'] .feature-icon {
    width: 76px;
    height: 76px;
    margin-bottom: 14px;
    font-size: 2.5rem;
}

html[data-print-orientation='landscape'] .feature-desc {
    margin: 8px auto 14px;
}

html[data-print-orientation='landscape'] .big-qr-canvas {
    width: 184px;
    height: 184px;
}

@media print {
    .print-root,
    html[data-print-orientation='landscape'] .print-root {
        min-height: 0;
        padding: 0;
        background: #fff;
    }

    .poster {
        border-radius: 0;
    }

    .poster,
    .qr-card,
    .feature-card,
    .tooth-icon,
    .feature-icon,
    .qr-canvas,
    .big-qr-canvas {
        box-shadow: none;
    }
}
</style>
