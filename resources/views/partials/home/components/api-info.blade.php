{{--
    Reference for using GPTalk through its OpenAI-compatible API under /api/v1/.
    Only rendered when ALLOW_EXTERNAL_COMMUNICATION is enabled; see modules/profile.blade.php.

    The model lists come from $apiModels, which HomeController fills from ApiCatalog - the
    same source as GET /api/v1/models, so what is shown here and what the API answers
    cannot drift apart.

    The older GPTalk-specific endpoint POST /api/ai-req still exists and is unchanged, but
    it is deliberately not documented here: it speaks a format no standard client can use.
--}}
@php
    $apiBase = url('/api/v1');
    $exampleModel = $apiModels['chat'][0] ?? 'model-id';
@endphp
<div class="api-info top-gap-2">
    <p class="zero-v-margin"><b>{{ $translation["Api_Info_Title"] }}</b></p>
    <p class="sub-descript gray-text zero-v-margin">{{ $translation["Api_Info_Format"] }}</p>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_BaseUrl"] }}</b></p>
    <p class="sub-descript zero-v-margin"><code>{{ $apiBase }}</code></p>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Auth"] }}</b></p>
    <p class="sub-descript zero-v-margin"><code>Authorization: Bearer &lt;TOKEN&gt;</code></p>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Endpoints"] }}</b></p>
    <table class="api-info-endpoints">
        <tr>
            <td><code>POST /chat/completions</code></td>
            <td>{{ $translation["Api_Info_Ep_Chat"] }}</td>
        </tr>
        <tr>
            <td><code>POST /completions</code></td>
            <td>{{ $translation["Api_Info_Ep_Completions"] }}</td>
        </tr>
        <tr>
            <td><code>POST /embeddings</code></td>
            <td>{{ $translation["Api_Info_Ep_Embeddings"] }}</td>
        </tr>
        <tr>
            <td><code>POST /rerank</code></td>
            <td>{{ $translation["Api_Info_Ep_Rerank"] }}</td>
        </tr>
        <tr>
            <td><code>GET /models</code></td>
            <td>{{ $translation["Api_Info_Ep_Models"] }}</td>
        </tr>
    </table>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Models_Chat"] }}</b></p>
    <p class="sub-descript zero-v-margin"><code>{{ implode(', ', $apiModels['chat']) }}</code></p>

    @if(!empty($apiModels['embedding']))
        <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Models_Embedding"] }}</b></p>
        <p class="sub-descript zero-v-margin"><code>{{ implode(', ', $apiModels['embedding']) }}</code></p>
    @endif

    @if(!empty($apiModels['rerank']))
        <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Models_Rerank"] }}</b></p>
        <p class="sub-descript zero-v-margin"><code>{{ implode(', ', $apiModels['rerank']) }}</code></p>
    @endif

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Example_Chat"] }}</b></p>
<pre class="api-info-code"><code>curl -X POST {{ $apiBase }}/chat/completions \
  -H "Authorization: Bearer &lt;TOKEN&gt;" \
  -H "Content-Type: application/json" \
  -d '{"model":"{{ $exampleModel }}",
       "messages":[{"role":"user","content":"Hallo"}]}'</code></pre>

    <p class="sub-descript zero-b-margin"><b>{{ $translation["Api_Info_Example_Models"] }}</b></p>
<pre class="api-info-code"><code>curl {{ $apiBase }}/models \
  -H "Authorization: Bearer &lt;TOKEN&gt;"</code></pre>

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

    .api-info-endpoints {
        margin: 0.3em 0 0.6em 0;
        border-collapse: collapse;
        font-size: var(--font-xs);
    }

    .api-info-endpoints td {
        padding: 0.15em 0.9em 0.15em 0;
        vertical-align: top;
    }

    .api-info-endpoints td:first-child {
        white-space: nowrap;
    }
</style>
