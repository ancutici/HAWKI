<?php
namespace App\Services\Chat\Attachment\Handlers;

use App\Services\FileConverter\FileConverterFactory;
use App\Services\FileConverter\Handlers\LocalFileConverter;
use Illuminate\Support\Str;

use App\Services\Storage\FileStorageService;
use App\Services\Chat\Attachment\Interfaces\AttachmentInterface;

use Exception;
use Illuminate\Support\Facades\Log;

class AtchDocumentHandler implements AttachmentInterface
{
    public function __construct(
        protected FileStorageService $storageService
    ){
    }

    public function store($file, string $category): array
    {
        // Generate a unique filename to prevent overwriting
        $uuid = Str::uuid();
        $originalName = $file->getClientOriginalName();

//        $stored = $this->storageService->store($file, $originalName, $uuid, $category, true);
        $stored = $this->storageService->store($file, $originalName, $uuid, $category, true);
        if (!$stored) {
            return [
                'success' => false,
                'message'=> 'Failed to store file.'
            ];
            // throw new \Exception('Failed to store file.');
        }
//        $url = $this->storageService->getUrl($uuid, $category);
        $results = $this->extractFileContent($file);

        if (!$results) {
            return [
                'success' => false,
                'uuid' => $uuid,
                'message'=> 'Failed to extract text from file'
            ];
            // throw new \Exception('Failed to store file.');
        }

        // Rough token-budget guard (~4 chars/token) so an oversized file can't silently
        // blow the model's context window later, at request time.
        $estimatedTokens = (int) ceil(array_sum(array_map('strlen', $results)) / 4);
        $maxEstimatedTokens = config('file_converter.max_estimated_tokens');
        if ($maxEstimatedTokens > 0 && $estimatedTokens > $maxEstimatedTokens) {
            return [
                'success' => false,
                'uuid' => $uuid,
                'reason' => 'content_too_large',
                'message' => "File content too large (~{$estimatedTokens} estimated tokens, limit {$maxEstimatedTokens}).",
            ];
        }

        foreach($results as $relativePath => $content){
            $this->storageService->store($content, basename($relativePath), $uuid, $category, true, '/output');
        }

        return [
            'success' => true,
            'uuid' => $uuid,
//            'url'=> $url
        ];
    }

    public function extractFileContent($file): ?array{
        try{
            $localTypes = ['txt', 'md', 'html', 'htm', 'csv', 'xlsx', 'xls', 'pptx',
                           'js', 'mjs', 'jsx', 'ts', 'tsx', 'css', 'json',
                           'yml', 'yaml', 'xml', 'php', 'py', 'sh', 'sql'];
            $ext = strtolower($file->getClientOriginalExtension());

            if (in_array($ext, $localTypes)) {
                return (new LocalFileConverter())->convert($file);
            }

            $converter = FileConverterFactory::create();
            return $converter->convert($file);
        }
        catch(Exception $e){
            return null;
        }
    }

    public function retrieveContext(string $uuid, string $category, $fileType = 'md'): string{
        $files = $this->storageService->retrieveOutputFilesByType($uuid, $category, $fileType);
        if($files || count($files) > 0){
            $results = [];
            foreach($files as $file){
                $content = $file['contents'];
                $html_safe = htmlspecialchars($content);
                $results[] = $html_safe;
            }
            return $results[0];
        }

        try{

            $file = $this->storageService->retrieve($uuid, $category);
            $results = $this->extractFileContent($file);

            if($results !== null){
                foreach($results as $relativePath => $content){
                    $this->storageService->store($content, basename($relativePath), $uuid, $category, true, '/output');
                }
                return $this->retrieveContext($uuid, $category);
            }
            else{
                return "Unable to extract content at the moment. please try again later. If the problem persists please contact the adminstrator.";
            }

        }
        catch(Exception $e){
            return "Unable to extract content at the moment. please try again later. If the problem persists please contact the adminstrator.";
        }

    }

}
