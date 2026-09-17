{{--
    Short reference for using GPTalk through the external API.
    Only rendered when ALLOW_EXTERNAL_COMMUNICATION is enabled; see modules/profile.blade.php.
--}}
<div class="api-info top-gap-2">
    <p class="zero-v-margin"><b>{{ $translation["Api_Info_Title"] }}</b></p>
    <p class="sub-descript gray-text zero-v-margin">{{ $translation["Api_Info_Experimental"] }}</p>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Endpoint"] }}</b></p>
    <p class="sub-descript zero-v-margin"><code>POST {{ url('/api/ai-req') }}</code></p>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Models"] }}</b></p>
    <p class="sub-descript zero-v-margin"><code>{{ implode(', ', $externalModelIds) }}</code></p>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Example"] }}</b></p>
<pre class="api-info-code"><code>curl -X POST {{ url('/api/ai-req') }} \
  -H "Authorization: Bearer &lt;TOKEN&gt;" \
  -H "Content-Type: application/json" \
  -d '{"payload":{"model":"{{ $externalModelIds[0] ?? 'model-id' }}",
       "messages":[{"role":"user","content":{"text":"Hallo"}}]}}'</code></pre>

    <p class="sub-descript gray-text zero-v-margin">{{ $translation["Api_Info_Limit"] }}</p>
</div>

<style>
    .api-info-code {
        padding: 0.6em 0.8em;
        margin: 0.3em 0 0.6em 0;
        overflow-x: auto;
        font-size: var(--font-xxs);
        white-space: pre;
    }
</style>
