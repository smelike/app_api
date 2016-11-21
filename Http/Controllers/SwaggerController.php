<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Swagger\Annotations as SWG;

use App\Http\Requests;

/**
 * @SWG\Swagger(
 *     schemes={"http"},
 *     host="qc02.xqopen.com",
 *     basePath="/lam/",
 *     produces={"application/json"},
 *     consumes={"application/json"},
 *     @SWG\Info(
 *         version="1.0.0",
 *         title="Lamp Project API",
 *         description="Backend API...",
 *         termsOfService="",
 *         @SWG\Contact(
 *             email="smelikecat@163.com"
 *         ),
 *         @SWG\License(
 *             name="Private License",
 *             url="URL to the license"
 *         )
 *     ),
 *     @SWG\Tag(name="Home", description="Roote Route"),
 *     @SWG\Tag(name="User", description="UserController"),
 *     @SWG\Tag(name="Role", description="RoleController"),
 *     @SWG\Tag(name="Customer", description="CustomerController"),
 *
 *     @SWG\ExternalDocumentation(
 *         description="Find out more about my website",
 *         url="http..."
 *     ),
 *    @SWG\Definition(
 *       definition="errorModel",
 *       required={"status code", "message"},
 *       @SWG\Property(
 *           property="status code",
 *           type="integer",
 *           format="int32"
 *       ),
 *       @SWG\Property(
 *           property="message",
 *           type="string"
 *       )
 *   ),
 *   @SWG\Definition(
 *     	definition="Login",
 *      @SWG\Property(
 *        	property="useraccount",
 *        	type="string"
 *      ),
 *      @SWG\Property(
 *         property="password",
 *         type="string"
 *      )
 * 	),
 *  @SWG\Definition(
 *       definition="logout",
 *       @SWG\Property(
 *           property="token",
 *           type="string"
 *       )
 *   ),
 *   @SWG\Definition(
 *       definition="resetpwd",
 *       @SWG\Property(
 *       	property="token",
 *         	type="string"
 *       ),
 *     @SWG\Property(
 *         property="new",
 *         type="string"
 *     ),
 *     @SWG\Property(
 *         property="old",
 *         type="string"
 *    )
 *   ),
 *   @SWG\Definition(
 *     	definition="create",
 *      @SWG\Property(
 *        	property="tel",
 *        	type="integer",
 *        	format="int32"
 *      ),
 *      @SWG\Property(
 *         property="name",
 *         type="string"
 *      ),
 *     @SWG\Property(
 *         property="password",
 *         type="string"
 *      ),
 *      @SWG\Property(
 *         property="position",
 *         type="integer"
 *      ),
 *    ),
 *    @SWG\Definition(
 *     	definition="edit",
 *      @SWG\Property(
 *        	property="id",
 *        	type="integer",
 *        	format="int32"
 *      ),
 *      @SWG\Property(
 *        	property="tel",
 *        	type="integer",
 *        	format="int32"
 *      ),
 *      @SWG\Property(
 *         property="name",
 *         type="string"
 *      ),
 *      @SWG\Property(
 *         property="resetpass",
 *         type="string"
 *      ),
 *      @SWG\Property(
 *         property="position",
 *         type="integer"
 *      )
 *    ),
 *    @SWG\Definition(
 *     	definition="del",
 *      @SWG\Property(
 *        	property="id",
 *        	type="integer",
 *        	format="int32"
 *      ),
 *    ),
 *    @SWG\Definition(
 *     	definition="query",
 *      @SWG\Property(
 *        	property="token",
 *        	type="string",
 *      ),
 *    ),
 *    @SWG\Definition(
 *     	definition="customer_query",
 *      @SWG\Property(
 *        	property="token",
 *        	type="string",
 *      ),
 *    ),
 * )
 */

class SwaggerController extends Controller
{
	/**
	 * @SWG\Get(
	 *   path="/",
	 *   summary="root route",
	 *   tags={"Outline"},
	 *   @SWG\Response(
	 *     response=200,
	 *     description="the root route"
	 *   ),
	 *   @SWG\Response(
	 *     response="default",
	 *     description="an unexpected error"
	 *   )
	 * )
	 */
	public $_debug;

	public function __construct(Request $request)
	{
		parent::__construct($request);
		$this->_debug = $request->input('debug');
	}

	public function doc()
	{
		$swagger = \Swagger\scan(realpath(__DIR__.'/../../'));
		if ($this->_debug) {
			return view('swagger.swagger', ['body' => $swagger]);
		} else {
			return response()->json($swagger);
		}
	}
}
