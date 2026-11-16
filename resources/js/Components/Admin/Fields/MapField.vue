<template>
  <div class="map-field">
    <label v-if="label" :for="name" class="block text-sm font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!--
      The combined input is deliberately the FIRST input in the wrapper:
      generic form drivers (and the browser test harness) fall back to
      `.admin-field input`, and a number input silently discards non-numeric
      keystrokes. Text first keeps "paste the whole thing" working everywhere.
    -->
    <input
      :id="name"
      type="text"
      data-map-value
      :value="combined"
      :placeholder="placeholder || combinedPlaceholder"
      :disabled="disabled"
      :readonly="readonly"
      class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
      :class="{ 'border-red-500': error }"
      @input="onCombinedInput"
      @change="onCombinedChange"
    />

    <div class="grid grid-cols-2 gap-2 mt-2">
      <div>
        <span class="block text-xs text-gray-500 mb-1">{{ $t('fields.map.latitude') }}</span>
        <!-- step="any" is required: the form has no novalidate, and the default
             step=1 would make 61.79862 invalid and block the whole submit. -->
        <input
          type="number"
          data-map-lat
          step="any"
          :value="lat"
          :placeholder="latPlaceholder"
          :disabled="disabled"
          :readonly="readonly"
          class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
          :class="{ 'border-red-500': error }"
          @input="onLatInput"
        />
      </div>

      <div>
        <span class="block text-xs text-gray-500 mb-1">{{ $t('fields.map.longitude') }}</span>
        <input
          type="number"
          data-map-lng
          step="any"
          :value="lng"
          :placeholder="lngPlaceholder"
          :disabled="disabled"
          :readonly="readonly"
          class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
          :class="{ 'border-red-500': error }"
          @input="onLngInput"
        />
      </div>
    </div>

    <div class="mt-2 flex items-center gap-3">
      <a
        v-if="mapUrl"
        :href="mapUrl"
        target="_blank"
        rel="noopener noreferrer"
        data-map-link
        class="text-sm text-indigo-600 hover:text-indigo-800"
      >
        {{ $t('fields.map.open_on_map') }}
      </a>

      <button
        v-if="hasValue && !disabled && !readonly"
        type="button"
        data-map-clear
        class="text-sm text-gray-400 hover:text-red-500"
        @click="clear"
      >
        {{ $t('fields.clear') }}
      </button>
    </div>

    <p v-if="showParseWarning" class="mt-1 text-sm text-amber-600">
      {{ $t('fields.map.parse_error', { example: '55.7558,37.6173' }) }}
    </p>

    <p v-if="hint && !error" class="mt-1 text-sm text-gray-500">{{ hint }}</p>
    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

/*
 * MapField — coordinate editor for Bitrix "map" properties.
 *
 * Storage contract (verified against the live data): the column is JSON but
 * every stored value is a *string* of the form "lat,lng"
 * (e.g. "61.79862,34.368981"). The model casts it to 'array', which for a
 * JSON scalar hands PHP — and therefore Vue — the plain string back.
 * So this component is string-in / string-out and never reformats the numbers:
 * emitting an object, or round-tripping through parseFloat, would rewrite the
 * on-disk shape of every record that is opened and saved.
 *
 * No map SDK is loaded on purpose: the admin pages must render without any
 * network access. The external link is the whole "map" affordance.
 */

const props = defineProps({
  modelValue: { type: [String, Number, Object, Array], default: null },

  // Standard field payload (declared so it does not leak into the DOM).
  name: { type: String, default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'map' },
  component: { type: String, default: null },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
  hint: { type: String, default: null },
  placeholder: { type: String, default: null },
  default: { type: [String, Number, Object, Array], default: null },
  attributes: { type: Object, default: () => ({}) },
  tab: { type: String, default: null },
  tabOrder: { type: Number, default: null },
  error: { type: String, default: null },

  // MapField-specific props (see MapField::getFieldProps()).
  mapType: { type: String, default: 'yandex' },
  defaultLat: { type: [Number, String], default: null },
  defaultLng: { type: [Number, String], default: null },
  defaultZoom: { type: [Number, String], default: null },
  // Accepted and ignored: address search needs a geocoder, i.e. network.
  searchEnabled: { type: Boolean, default: false },
  apiKey: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const COORD_RE = /^-?\d+(\.\d+)?$/;

// All three refs hold STRINGS. Never numbers — Number("55.100") would come
// back out as "55.1" and silently change stored precision.
const lat = ref('');
const lng = ref('');
const combined = ref('');

// Guards the watcher against the component's own emit: while the user types
// "55." into the latitude box we emit "55.," and it echoes straight back.
// Re-parsing that echo would blank the box mid-keystroke.
let lastEmitted;
let hasEmitted = false;

/**
 * Normalise anything the backend might hand us into {lat, lng, raw, ok}.
 * Never throws: null / undefined / '' / numbers / legacy objects all land here.
 */
function parseValue(value) {
  const empty = { lat: '', lng: '', raw: '', ok: false };

  if (value === null || value === undefined) {
    return empty;
  }

  // Defensive: the public template also tolerates {lat,lng} and [lat,lng].
  if (Array.isArray(value)) {
    return fromParts(value[0], value[1]);
  }

  if (typeof value === 'object') {
    return fromParts(
      value.lat ?? value.latitude ?? value[0],
      value.lng ?? value.lon ?? value.longitude ?? value[1],
    );
  }

  // Some legacy rows were stored with their JSON quotes baked in.
  const raw = String(value).trim().replace(/^"+|"+$/g, '').trim();

  if (raw === '') {
    return empty;
  }

  const parts = raw.split(',');
  const rawLat = (parts[0] ?? '').trim();
  const rawLng = (parts[1] ?? '').trim();
  const latOk = COORD_RE.test(rawLat);
  const lngOk = COORD_RE.test(rawLng);

  return {
    // A number input blanks a value it considers invalid, so keep the ref in
    // step with the DOM rather than letting the two drift apart.
    lat: latOk ? rawLat : '',
    lng: lngOk ? rawLng : '',
    raw,
    ok: latOk && lngOk && parts.length === 2,
  };
}

function fromParts(rawLat, rawLng) {
  const a = rawLat === null || rawLat === undefined ? '' : String(rawLat).trim();
  const b = rawLng === null || rawLng === undefined ? '' : String(rawLng).trim();
  const latOk = COORD_RE.test(a);
  const lngOk = COORD_RE.test(b);

  return {
    lat: latOk ? a : '',
    lng: lngOk ? b : '',
    raw: a === '' && b === '' ? '' : `${a},${b}`,
    ok: latOk && lngOk,
  };
}

function applyValue(value) {
  const parsed = parseValue(value);
  lat.value = parsed.lat;
  lng.value = parsed.lng;
  combined.value = parsed.raw;
}

watch(
  () => props.modelValue,
  (value) => {
    if (hasEmitted && value === lastEmitted) {
      return;
    }
    applyValue(value);
  },
  { immediate: true },
);

function push(value) {
  lastEmitted = value;
  hasEmitted = true;
  emit('update:modelValue', value);
}

/** Rebuild "lat,lng" from the two numeric boxes and emit it. */
function emitFromParts() {
  const a = lat.value.trim();
  const b = lng.value.trim();

  if (a === '' && b === '') {
    combined.value = '';
    push(null);
    return;
  }

  // A half-filled pair still emits "55.1," rather than dropping the user's
  // input; the public template reads that as "no coordinates" and degrades.
  combined.value = `${a},${b}`;
  push(combined.value);
}

function onLatInput(event) {
  lat.value = event.target.value;
  emitFromParts();
}

function onLngInput(event) {
  lng.value = event.target.value;
  emitFromParts();
}

function onCombinedInput(event) {
  const value = event.target.value;
  combined.value = value;

  const parsed = parseValue(value);
  lat.value = parsed.lat;
  lng.value = parsed.lng;

  const trimmed = value.trim();
  push(trimmed === '' ? null : trimmed);
}

/** On blur, tidy up "55.1, 37.2" → "55.1,37.2" — but only when unambiguous. */
function onCombinedChange() {
  const parsed = parseValue(combined.value);

  if (!parsed.ok) {
    return;
  }

  const normalised = `${parsed.lat},${parsed.lng}`;

  if (normalised !== combined.value) {
    combined.value = normalised;
    push(normalised);
  }
}

function clear() {
  lat.value = '';
  lng.value = '';
  combined.value = '';
  push(null);
}

const parsed = computed(() => parseValue(combined.value));

const hasValue = computed(() => combined.value !== '');

const showParseWarning = computed(() => hasValue.value && !parsed.value.ok);

const mapUrl = computed(() => {
  if (!parsed.value.ok) {
    return null;
  }

  const query = encodeURIComponent(`${parsed.value.lat},${parsed.value.lng}`);

  if (props.mapType === 'google') {
    return `https://www.google.com/maps/search/?api=1&query=${query}`;
  }

  // `text=` takes lat,lng — the same order we store. Yandex's `ll=`/`pt=`
  // params take LONGITUDE first, so they would need a swap; this does not.
  const zoom = Number(props.defaultZoom);
  const z = Number.isFinite(zoom) && zoom > 0 ? `&z=${zoom}` : '';

  return `https://yandex.ru/maps/?text=${query}${z}`;
});

// Defaults are placeholder text only. Pre-filling them would stamp Moscow onto
// every record whose coordinates are NULL the first time the form is saved.
const latPlaceholder = computed(() => (props.defaultLat === null ? '55.7558' : String(props.defaultLat)));
const lngPlaceholder = computed(() => (props.defaultLng === null ? '37.6173' : String(props.defaultLng)));
const combinedPlaceholder = computed(() => `${latPlaceholder.value},${lngPlaceholder.value}`);
</script>
