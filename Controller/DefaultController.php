<?php

/*
 * This file is part of the Snowcap ImBundle package.
 *
 * (c) Snowcap <shoot@snowcap.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Snowcap\ImBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

use Snowcap\ImBundle\Exception\RuntimeException;

/**
 * Controls calls to resized images
 */
class DefaultController extends Controller
{
    /**
     * Main action: renders the image cache and returns it to the browser
     *
     * @param string $format A format name defined in config or a string [width]x[height]
     * @param string $path   The path of the source file
     *
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Snowcap\ImBundle\Exception\RuntimeException
     */
    public function indexAction($format, $path)
    {
        /** @var $im \Snowcap\ImBundle\Manager */
        $im = $this->get("snowcap_im.manager");

        /** @var $kernel \Symfony\Component\HttpKernel\Kernel */
        $kernel = $this->get('kernel');

        if (strpos($path, "http/") === 0 || strpos($path, "https/") === 0) {
            $protocol = substr($path, 0, strpos($path, "/"));
            if (!$im->cacheExists($format, $path)) {
                $newPath = str_replace($protocol . "/", $kernel->getRootDir() . '/../web/cache/im/' . $format . '/' . $protocol . '/', $path);

                @mkdir(dirname($newPath), 0755, true);

                $fp = fopen($newPath, 'w');

                $ch = curl_init(str_replace($protocol . '/', $protocol . '://', $path));
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_HEADER, 0);

                curl_exec($ch);
                curl_close($ch);
                fclose($fp);

                $im->mogrify($format, $newPath);
            }
        } else {
            $im->convert($format, $path);
        }

        if (!$im->cacheExists($format, $path)) {
            throw new RuntimeException(sprintf("Caching of image failed for %s in %s format", $path, $format));
        } else {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            ;
            $contentType = $this->getRequest()->getMimeType($extension);
            if (empty($contentType)) {
                $contentType = 'image/' . $extension;
            }

            return new Response($im->getCacheContent($format, $path), 200, array('Content-Type' => $contentType));
        }
    }
}
