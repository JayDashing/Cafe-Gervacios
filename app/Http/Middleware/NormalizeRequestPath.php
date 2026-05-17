<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeRequestPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (! str_contains($path, '//')) {
            return $next($request);
        }

        $normalizedPath = preg_replace('#/+#', '/', $path) ?: '/';

        if ($normalizedPath === $path || ! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $query = $request->getQueryString();
        $target = $request->getSchemeAndHttpHost().$request->getBaseUrl().$normalizedPath;

        if ($query !== null && $query !== '') {
            $target .= '?'.$query;
        }

        return redirect()->to($target);
    }
}
