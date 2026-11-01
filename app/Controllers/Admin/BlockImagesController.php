<?php

namespace App\Controllers\Admin;

use CodeIgniter\HTTP\ResponseInterface;

class BlockImagesController extends AdminController
{
    public function store(): ResponseInterface
    {
        $result = service('blockImage')->store($this->request->getFile('image'));
        $data   = [
            'files'    => [],
            'baseurl'  => '',
            'messages' => [],
        ];

        if ($result['ok']) {
            $data['files']   = [$result['name']];
            $data['baseurl'] = $result['baseurl'];
            $data['isImages'] = [true];
        } else {
            $data['messages'] = [$result['error']];
        }

        return $this->response
            ->setStatusCode($result['ok'] ? 200 : 422)
            ->setJSON([
                'success' => $result['ok'],
                'time'    => date('Y-m-d H:i:s'),
                'csrf'    => service('security')->getHash(),
                'data'    => $data,
            ]);
    }
}
