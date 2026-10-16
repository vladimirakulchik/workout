<script setup lang="ts">
import type { RadioGroupItemProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { RadioGroupIndicator, RadioGroupItem, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<RadioGroupItemProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
const forwardedProps = useForwardProps(delegatedProps)
</script>

<template>
  <RadioGroupItem
    data-slot="radio-group-item"
    v-bind="forwardedProps"
    :class="cn(
      'aspect-square size-[22px] shrink-0 rounded-full border border-input text-brand outline-none transition-shadow focus-visible:border-brand focus-visible:ring-brand/25 focus-visible:ring-[3px] aria-invalid:border-destructive disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:border-brand',
      props.class,
    )"
  >
    <RadioGroupIndicator class="flex items-center justify-center">
      <span class="size-2.5 rounded-full bg-brand" />
    </RadioGroupIndicator>
  </RadioGroupItem>
</template>
