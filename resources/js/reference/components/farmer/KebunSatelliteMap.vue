<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/components/farmer/KebunSatelliteMap.vue -->
<script setup lang="ts">
defineProps<{ position: [number, number] }>();
</script>
<template><div class="flex h-48 items-center justify-center rounded-xl bg-muted p-4 text-center text-xs text-muted-foreground">Pratinjau koordinat historis: {{ position.join(', ') }}. Citra satelit eksternal dinonaktifkan pada referensi.</div></template>
