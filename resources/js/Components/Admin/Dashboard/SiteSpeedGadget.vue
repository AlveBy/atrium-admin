<script setup>
/**
 * «Скорость сайта» — плитка рабочего стола.
 *
 * Раскладка и палитра сняты с гаджета Битрикса
 * (bitrix/gadgets/bitrix/admin_sitespeed/site_speed.css): зелёная карточка,
 * крупное число справа, линейка 0…2,5 сек и пять цветных полос. Картинки
 * оттуда не тащим — рисуем своим CSS, цвета взяты как значения.
 */
defineProps({
    title: { type: String, default: 'Скорость сайта' },
    data: { type: Object, required: true },
})

const BAND_COLORS = ['#cddd62', '#afc232', '#ecd238', '#eaaa3a', '#eb8825']
</script>

<template>
    <div class="speed-card rounded-lg shadow px-5 py-4">
        <div class="flex items-start justify-between gap-4">
            <div class="speed-title">
                {{ title }}:
                <span class="speed-label">{{ data.label }}</span>
            </div>
            <div v-if="data.seconds" class="speed-num whitespace-nowrap">
                {{ data.seconds }}<span class="speed-unit">{{ data.unit }}</span>
            </div>
        </div>

        <div class="mt-3">
            <div class="speed-ruler">
                <span>0</span>
                <span>{{ data.scaleSeconds }}</span>
            </div>

            <div class="speed-graph">
                <span
                    v-for="(band, index) in data.bands"
                    :key="band"
                    class="speed-band"
                    :style="{ backgroundColor: BAND_COLORS[index] }"
                >
                    <span class="speed-band-text">{{ band }}</span>
                </span>

                <div
                    v-if="data.percent !== null"
                    class="speed-pointer"
                    :style="{ left: data.percent + '%' }"
                >
                    <div class="speed-pointer-value">{{ data.seconds }}</div>
                </div>
            </div>
        </div>

        <p class="speed-note">
            <template v-if="data.samples">
                медиана по {{ data.samples }} замерам за {{ data.windowDays }} дней
            </template>
            <template v-else>
                замеров пока нет — числа появятся после первых заходов на сайт
            </template>
        </p>
    </div>
</template>

<style scoped>
.speed-card {
    background-image: linear-gradient(to bottom, #a5bf3c, #96b02a, #86a21c);
    color: #4a5813;
}

.speed-title {
    font-size: 15px;
    font-weight: 700;
    text-shadow: 0 1px 0 rgba(243, 244, 188, 0.73);
}

.speed-label {
    border-bottom: 1px solid #4a5813;
    font-weight: 400;
}

.speed-num {
    font-size: 38px;
    font-weight: 700;
    line-height: 1;
    text-shadow: 0 1px rgba(221, 231, 233, 0.44);
}

.speed-unit {
    font-size: 14px;
    font-weight: 400;
    margin-left: 4px;
}

.speed-ruler {
    color: #576812;
    display: flex;
    font-size: 11px;
    justify-content: space-between;
    margin-bottom: 3px;
    text-shadow: 0 1px #fff;
}

.speed-graph {
    border-radius: 3px;
    box-shadow: 0 6px 2px -5px #425202, 0 11px 2px -10px #637a04;
    display: flex;
    height: 40px;
    position: relative;
}

.speed-band:first-child {
    border-radius: 3px 0 0 3px;
}

.speed-band:last-child {
    border-radius: 0 3px 3px 0;
}

.speed-band {
    align-items: center;
    color: #1d2331;
    display: flex;
    flex: 1 1 20%;
    font-size: 8px;
    justify-content: center;
    line-height: 10px;
    padding: 0 2px;
    text-align: center;
    text-shadow: 0 1px rgba(221, 231, 233, 0.7);
    text-transform: uppercase;
}

.speed-pointer {
    background-color: rgba(74, 88, 19, 0.32);
    bottom: 0;
    height: 40px;
    position: absolute;
    transition: left 0.55s cubic-bezier(0.23, 1, 0.32, 1);
    width: 1px;
}

/* Как в Битриксе — плашка со значением стоит НАД шкалой (top: -22px),
   а не поверх полос, иначе она закрывает подпись под указателем. */
.speed-pointer-value {
    background: #f3f4bc;
    border-radius: 3px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
    color: #000;
    font-size: 12px;
    font-weight: 700;
    left: -22px;
    line-height: 19px;
    position: absolute;
    text-align: center;
    top: -22px;
    width: 44px;
}

.speed-note {
    color: #46540f;
    font-size: 11px;
    margin-top: 10px;
}
</style>
