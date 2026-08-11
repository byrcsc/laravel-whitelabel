{{-- Written unescaped on purpose: a <style> element is CSS raw text, so HTML
     escaping here would corrupt any value containing & or a quote rather than
     protect anything. The component has already cleaned names and values. --}}
<style {{ $attributes }}>:root{@foreach ($variables as $name => $value){!! $name !!}:{!! $value !!};@endforeach}</style>
