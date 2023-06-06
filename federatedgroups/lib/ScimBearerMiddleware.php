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

class ScimBearerMiddleware extends Middleware{
    
    /**
     * @var IRequest $request
     */
    private $request; 
    
    public function __construct(IRequest $request){
        $this->request = $request;
    }

    public function beforeController($controller, $methodName) {
        if(get_class($controller) === ScimController::class){
            error_log("x-auth: ".$this->request->getHeader("x-auth"));
            error_log("authorization: ".$this->request->getHeader("Authorization"));
            error_log("\$_Server: ".json_encode($_SERVER));
            if ($this->request->getHeader("x-auth") === null || $this->request->getHeader("x-auth") === ''){
                throw new ForbiddenException("x-auth header is required."); 
            }
        }
	}

    public function afterException($controller, $methodName, \Exception $exception) {
        if(get_class($exception) === ForbiddenException::class){  
            return new JSONResponse(["message" => $exception->getMessage()], Http::STATUS_FORBIDDEN);
        }
	}
}
