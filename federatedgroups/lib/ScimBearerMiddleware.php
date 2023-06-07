<?php
/**
 * @author Aaron Wood <aaronjwood@gmail.com>
 * @author Roeland Jago Douma <rullzer@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Navid Shokri <navid.pdp11@gmail.com>
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\FederatedGroups;

use Exception;
use OC\ForbiddenException;
use OCA\FederatedGroups\Controller\ScimController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Middleware;
use OCP\IRequest;
use OCP\AppFramework\Http;
use OCP\IConfig;

class ScimBearerMiddleware extends Middleware
{
    
    private $appName = "federatedgroups";
    private $tokenKey = "scim_token";

    /**
     * @var IRequest $request
     */
    private $request; 
    /**
     * @var IConfig $config
     */
    private $config;

    public function __construct(IRequest $request, $config)
    {
        $this->request = $request;
        $this->config = $config;
        
    }

    public function beforeController($controller, $methodName) {
        if(get_class($controller) === ScimController::class){
            $reqToken = $this->request->getHeader("x-auth");
            if ( $reqToken === null || $reqToken === ''){
                throw new ForbiddenException("x-auth header is required."); 
            }
            else {
                $refToken = $this->getScimToken(); 
                if ("Bearer {$refToken}" !== $reqToken){
                    throw new ForbiddenException("invalide x-auth header provided."); 
                }
            }
        }
	}

    public function afterException($controller, $methodName, \Exception $exception) {
        if(get_class($exception) === ForbiddenException::class){  
            return new JSONResponse(["message" => $exception->getMessage()], Http::STATUS_FORBIDDEN);
        }
	}

    private function getScimToken (){
        $token = $this->config->getAppValue($this->appName, $this->tokenKey);
        if  ($token === null || $token === ''){
            $token = $this->generateRandomString(32);
            $this->config->setAppValue($this->appName, $this->tokenKey, $token);
        }
        return $token;
    }

    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
