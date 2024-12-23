<?php

namespace Yggdrasil\Controllers;

use Illuminate\Routing\Controller;
use App\Services\Plugin;
use App\Services\PluginManager;
use App\Services\Unzip;
use Composer\CaBundle\CaBundle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class UpdateController extends Controller {
    public function update(Request $request, PluginManager $manager, Unzip $unzip) {
      	if (option('union_enable_update')) {
            $data = $request->validate(['url' => 'required|url', 'plugin' => 'filled|string']);
          	$plugin_id = isset($data['plugin']) ? $data['plugin'] : 'yggdrasil-api';

            $path = tempnam(sys_get_temp_dir(), 'wget-plugin');
            $response = Http::withOptions([
                'sink' => $path,
                'verify' => CaBundle::getSystemCaRootBundlePath(),
            ])->get($data['url']);

            if ($response->ok()) {
                $unzip->extract($path, $manager->getPluginsDirs()->first());
                $plugin = plugin('yggdrasil-api');
                $manager->disable($plugin);
                $manager->enable($plugin);
                return;
            }
            abort($response->status());
        }
    }
}