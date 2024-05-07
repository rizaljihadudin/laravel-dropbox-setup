<?php

namespace App\Http\Controllers;

use App\Models\Dropfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\Dropbox\Client as DropboxClient;
use GrahamCampbell\GuzzleFactory\GuzzleFactory;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\StreamWrapper;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Response;

class DropfileController extends Controller
{
    protected $dropbox;

    public function __construct()
    {
        $this->dropbox = new DropboxClient(config('services.dropbox.token'));
        $this->client = $client ?? new GuzzleClient(['handler' => GuzzleFactory::handler()]);
    }

    public function index()
    {
        $files = Dropfile::all();
        $allFiles = collect(Storage::disk('dropbox')->files('audit'))->map(function($file) {
            return Storage::disk('dropbox')->url($file);
        });

        return view('pages.drop-index', [
            'url'   => $allFiles,
            'files' => $files
        ]);
    }

    public function store(Request $request)
    {
        try {
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileExtension = $file->getClientOriginalExtension();
                $mimeType = $file->getClientMimeType();
                $fileSize = $file->getSize();
                $filename = $file->getClientOriginalName();

                Storage::disk('dropbox')->putFileAs('audit/', $file, $filename);
                // $this->dropbox->createSharedLinkWithSettings('audit/' . $filename);
                $this->createSharedLinkWithSettings('/audit/' . $filename, [
                    "access"                =>"viewer",
                    "allow_download"        => true,
                    "audience"              => "public",
                    "requested_visibility"  => "public"
                ]);
                Dropfile::create([
                    'file_title' => $filename,
                    'file_type' => $mimeType,
                    'file_size' => $fileSize,
                ]);

                return redirect('drop');
            }
        } catch (\Exception $e) {
            dd($e);
        }
    }

    public function show($fileTitle)
    {
        try {
            $link = $this->listSharedLinks('/audit/' . $fileTitle);
            $raw = explode("?", $link[0]['url']);
            $path = $raw[0] . "?raw=1";
            $tempPath = tempnam(sys_get_temp_dir(), $path);
            $copy = copy($path, $tempPath);

            return redirect($raw[0]);
        } catch (\Exception $e) {
            return abort(404);
        }
    }

    public function download($fileTitle)
    {
        // dd($this->downloadZip('/audit/'));
        try {
            // return Storage::disk('dropbox')->download('audit/' . $fileTitle);

            return $this->downloadZip('/audit/');
        } catch (\Exception $e) {
            return abort(404);
        }
    }

    public function destroy($id)
    {
        try {
            $file = Dropfile::find($id);
            Storage::disk('dropbox')->delete('audit/' . $file->file_title);
            $file->delete();

            return redirect('drop');
        } catch (\Exception $e) {
            return abort(404);
        }
    }

    #created shared link
    public function createSharedLinkWithSettings(string $path, array $settings = []): array
    {
        $parameters = [
            'path' => $path,
        ];
        if (count($settings)) {
            $parameters = array_merge(compact('settings'), $parameters);
        }

        return $this->rpcEndpointRequest('sharing/create_shared_link_with_settings', $parameters);
    }

    #get shared links
    public function listSharedLinks(string $path = null, bool $direct_only = false, string $cursor = null): array
    {
        $parameters = [
            'path' => $path,
            'cursor' => $cursor,
            'direct_only' => $direct_only,
        ];

        $body = $this->rpcEndpointRequest('sharing/list_shared_links', $parameters);

        return $body['links'];
    }

    #download zip
    public function downloadZip(string $path)
    {
        $arguments = [
            'path' => $path,
        ];

        $response = $this->contentEndpointRequest('files/download_zip', $arguments);
        $fileContent = $response->getBody()->getContents();
        $httpResponse = new Response($fileContent);
        $httpResponse->header('Content-Type', 'application/zip');
        $httpResponse->header('Content-Disposition', 'attachment; filename="' . basename($path) . '.zip"');

        return $httpResponse;
    }

    protected function normalizePath(string $path): string
    {
        return ltrim($path, '/');
    }

    public function rpcEndpointRequest(string $endpoint, array $parameters = null, bool $isRefreshed = false): array
    {
        try {
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . env('DROPBOX_ACCESS_TOKEN'),
                ]
            ];

            if ($parameters) {
                $options['json'] = $parameters;
            }

            $response = $this->client->request('POST', $this->getEndpointUrl('api', $endpoint), $options);
            return json_decode($response->getBody(), true) ?? [];
        } catch (\Exception $exception) {
            dd($exception);
        }
    }

    protected function getEndpointUrl(string $subdomain, string $endpoint): string
    {
        if (count($parts = explode('::', $endpoint)) === 2) {
            [$subdomain, $endpoint] = $parts;
        }

        return "https://{$subdomain}.dropboxapi.com/2/{$endpoint}";
    }

    public function contentEndpointRequest(string $endpoint, array $arguments, mixed $body = '', bool $isRefreshed = false): ResponseInterface
    {
        try {
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('DROPBOX_ACCESS_TOKEN'),
                    'Dropbox-API-Arg' => json_encode($arguments),
                ]
            ];

            $response = $this->client->request('POST', $this->getEndpointUrl2('api', $endpoint), $options);
        } catch (\Exception $exception) {
            dd($exception);
        }
        return $response;
    }

    protected function getEndpointUrl2(string $subdomain, string $endpoint): string
    {
        return "https://content.dropboxapi.com/2/{$endpoint}";
    }


}

