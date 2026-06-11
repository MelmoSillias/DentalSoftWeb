<template>
    <div class="single-page">
        <article :class="entry.key === 'showcase' ? 'feature-card' : 'qr-card'">
            <div :class="entry.key === 'showcase' ? 'feature-icon' : 'card-icon'">
                <i :class="entry.iconClass"></i>
            </div>
            <h1>{{ entry.title }}</h1>
            <p :class="entry.key === 'showcase' ? 'feature-desc' : 'qr-desc'">
                {{ entry.description }}
            </p>

            <div class="qr-wrapper">
                <img v-if="entry.imageSrc" :src="entry.imageSrc" :alt="entry.title" :class="entry.key === 'showcase' ? 'qr-image large' : 'qr-image'" />
                <div v-else class="qr-placeholder">URL non renseignee</div>
            </div>

            <div :class="entry.key === 'showcase' ? 'big-badge' : 'badge-qr'">{{ entry.badge }}</div>

            <div class="qr-url">
                {{ entry.url || 'URL non renseignee' }}
            </div>
        </article>
    </div>
</template>

<script setup>
defineProps({
    entry: {
        type: Object,
        required: true
    }
});
</script>

<style scoped>
.single-page {
    min-height: 100vh;
    padding: 24px;
    background: #eef5f8;
    display: grid;
    place-items: center;
}

.qr-card,
.feature-card {
    width: min(100%, 150mm);
    background: #fff;
    border-radius: 28px;
    padding: 24px;
    text-align: center;
    border: 1px solid rgba(44, 155, 143, 0.28);
    box-shadow: 0 16px 30px -16px rgba(0, 0, 0, 0.25);
}

.feature-card {
    border-radius: 34px;
    background: linear-gradient(145deg, #fff 0%, #fbfefe 100%);
}

.card-icon,
.feature-icon {
    width: 74px;
    height: 74px;
    border-radius: 999px;
    margin: 0 auto 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1f6e8c;
    background: #eef7f5;
    font-size: 2.1rem;
}

.feature-icon {
    width: 86px;
    height: 86px;
    color: #fff;
    background: linear-gradient(135deg, #1f6e8c, #2c9b8f);
}

h1 {
    margin: 0;
    color: #1f3b43;
    font-size: 1.9rem;
}

.qr-desc,
.feature-desc {
    margin: 10px auto 16px;
    color: #577e8f;
    line-height: 1.45;
    max-width: 480px;
}

.qr-wrapper {
    display: flex;
    justify-content: center;
    margin: 10px 0;
}

.qr-image {
    width: 210px;
    height: 210px;
    object-fit: cover;
    border-radius: 24px;
    padding: 10px;
    border: 1px solid #d7e3e7;
    background: #fff;
}

.qr-image.large {
    width: 250px;
    height: 250px;
    border-radius: 28px;
}

.badge-qr,
.big-badge {
    display: inline-block;
    margin-top: 12px;
    padding: 6px 16px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-qr {
    background: #f0f6f9;
    color: #2c9b8f;
}

.big-badge {
    background: #e0f0ed;
    color: #1f6e8c;
}

.qr-url {
    margin-top: 14px;
    font-size: 0.78rem;
    color: #547888;
    word-break: break-all;
}

.qr-placeholder {
    width: 210px;
    height: 210px;
    border-radius: 24px;
    border: 1px dashed #c2d4dc;
    color: #6b8794;
    display: grid;
    place-items: center;
    padding: 10px;
}

@media print {
    .single-page {
        background: #fff;
        padding: 0;
    }

    .qr-card,
    .feature-card,
    .qr-image {
        box-shadow: none;
    }

    @page {
        size: A5;
        margin: 1cm;
    }
}
</style>
