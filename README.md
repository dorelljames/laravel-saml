# Laravel SAML for 5.1

Laravel-SAML implements a SAML2 IDP plugin to transform laravel into a SAML identity provider (IDP) beside the regular authentication. The package is designed to work with Laravel 5.1. This is a fork from [kingstarter/laravel-saml](https://github.com/kingstarter/laravel-saml) which is intended for Laravel 5.4 or above. Haven't tested with Laravel 5.2 or 5.3 though PR's are welcome or you can just test as it required me little work to get this working on Laravel 5.1, just let me know.

The package is based on [Dustin Parham's guide to implement a SAML IDP with laravel](https://imbringingsyntaxback.com/implementing-a-saml-idp-with-laravel/). To get a better basic understanding for SAML in general, read [Cheung's SAML for Web Developers](https://github.com/jch/saml).

## Installation

### Basic package installation

Using ```composer```: 

``` 
composer require "dorelljames/laravel-saml-5.1":"dev-master"
```

#### Lightsaml dependency problem

In case you run in a current lightsaml dependency problem regarding symfony 4 (event dispatcher) you might consider [using a fork of lightsaml allowing to use symfony 4](https://github.com/kingstarter/laravel-saml/issues/8#issuecomment-366991715).

#### Laravel 5.1
Add the service provider to ```config/app.php```

```
    KingStarter\LaravelSaml\LaravelSamlServiceProvider::class,
```
#### Configuration
There is one configuration file to publish and the config/filesystem.php file that needs to be extended. The command
```
php artisan vendor:publish --tag="saml_config"
```

will publish the `config/saml.php` file. 

#### SAML SP entries

Within the `saml.php` config file the SAML Service Provider array needs to be filled. Subsequently an example from the `config/saml.php` file:

```
'sp' => [        
    
    /**
     * Sample SP entry
     * The entry is identified by the base64 encoded URL. This example shows a possible entry for
     * a SimpleSamlPhp service provider running on localhost:
     * 
     * Sample URL:         https://localhost/samlsp/module.php/saml/sp/saml2-acs.php/default-sp
     * Base64 encoded URL: aHR0cHM6Ly9sb2NhbGhvc3Qvc2FtbHNwL21vZHVsZS5waHAvc2FtbC9zcC9zYW1sMi1hY3MucGhwL2RlZmF1bHQtc3A=
     */
    'aHR0cHM6Ly9sb2NhbGhvc3Qvc2FtbHNwL21vZHVsZS5waHAvc2FtbC9zcC9zYW1sMi1hY3MucGhwL2RlZmF1bHQtc3A=' => [
    
        // The destination is the consuming SAML URL. This might be a SamlAuthController receiving the SAML response.  
        'destination' => 'https://localhost/samlsp/module.php/saml/sp/saml2-acs.php/default-sp',
        // Issuer could be anything, mostly it makes sense to pass the metadata URL
        'issuer' => 'https://localhost',
        
        // OPTIONAL: Use a specific audience restriction value when creating the SAMLRequest object.
        //           Default value is the assertion consumer service URL (the base64 encoded SP url). 
        //           This is a bugfix for Nextcloud as SP and can be removed for normal SPs.
        'audience_restriction' => 'http://localhost',
    ],
    
],
```

You can generate the base_64 encoded AssertionURL by using the following artisan command.
 
```bash
   $ php artisan laravel-saml:encodeurl https://sp.webapp.com/saml/login
   --
   URL Given: https://sp.webapp.com/saml/login
   Encoded AssertionURL:aHR0cHM6Ly9zcC53ZWJhcHAuY29tL3NhbWwvbG9naW4=
```

config/saml.php:
```
'sp' => [        
    
     ...

    /**
     * New entry
     * 
     * Sample URL:         https://sp.webapp.com/saml/login
     * Base64 encoded URL: aHR0cHM6Ly9zcC53ZWJhcHAuY29tL3NhbWwvY29uc3VtZQ==
     */
    'aHR0cHM6Ly9zcC53ZWJhcHAuY29tL3NhbWwvY29uc3VtZQ==' => [
        'destination' => 'https://sp.webapp.com/saml/consume',
        'issuer'      => 'https://sp.webapp.com',
    ],
],
```

#### FileSystem configuration 

Within ```config/filesystem.php``` following entry needs to be added:
```
    'disks' => [

        ...
        
        'saml' => [
            'driver' => 'local',
            'root' => storage_path().'/saml',
        ],

    ],
```

The package controllers are using the ```storage/saml``` path for retrieving both certificates and the metadata file. Create first the storage path, then either add or link the certificates. Add also a metadata file for the SAML IDP. For help generating an IDP metadata.xml file, see https://www.samltool.com/idp_metadata.php.

```
mkdir -p storage/saml/idp
touch storage/saml/idp/{metadata.xml,cert.pem,key.pem}
```

Add the contents to the metadata.xml, cert.pem and key.pem files for the IDP. 

### Using the SAML package

To use the SAML package, some files need to be modified. Within your login view, problably ```resources/views/auth/login.blade.php``` add a SAMLRequest field beneath the CSRF field (this is actually a good place for it):
```php
    {{-- The hidden CSRF field for secure authentication --}}
    {{ csrf_field() }}
    {{-- Add a hidden SAML Request field for SAML authentication --}}
    @if(isset($_GET['SAMLRequest']))
        <input type="hidden" id="SAMLRequest" name="SAMLRequest" value="{{ $_GET['SAMLRequest'] }}">
    @endif
```

The SAMLRequest field will be filled automatically when a SAMLRequest is sent by a http request and therefore initiate a SAML authentication attempt. To initiate the SAML auth, the login and redirect functions need to be modified.

Within ```app/Http/Controllers/Auth/AuthenticatesUsers.php``` add following lines to `postLogin`, `handleUserWasAuthenticated` functions respectively: 
(NOTE: you might need to copy it out from vendor/laravel/framework/src/Illuminate/Foundation/Auth/ to your Middleware directory) 

```
<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Foundation\Auth\RedirectsUsers;

use KingStarter\LaravelSaml\Http\Traits\SamlAuth;

trait AuthenticatesUsers
{
    use RedirectsUsers, SamlAuth;
    
    ...

    public function postLogin(Request $request)
    {
        ...

        // Preserve SAMLRequest when found as login is reattempted
        $loginPath = isset($request['SAMLRequest']) ? $this->loginPath() . "?SAMLRequest=" . urlencode($request['SAMLRequest']) : $this->loginPath();

        return redirect($loginPath)
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors([
                $this->loginUsername() => $this->getFailedLoginMessage(),
            ]);
    }
    
    protected function handleUserWasAuthenticated(Request $request, $throttles)
    {
        // Forward request to SAML SP after successful auth
        if (Auth::check() && isset($request['SAMLRequest'])) {
            $this->handleSamlLoginRequest($request);
        }

        ...
    }
    
    ...
```

To use the trait `AuthenticatesUsers` we need to tell `AuthController` to use it but by which is used in `AuthenticatesAndRegistersUsers` trait. Therefore, go to `vendor/laravel/framework/src/Illuminate/Foundation/Auth/` and copy `AuthenticatesAndRegistersUsers.php` to `app/Http/Controllers/Auth/AuthenticatesAndRegistersUsers.php`.

Make sure you update namespace and tell where `ReggistersUsers` is located by `use Illuminate\Foundation\Auth\RegistersUsers;`.  It should like this below:
```
<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Foundation\Auth\RegistersUsers;

trait AuthenticatesAndRegistersUsers
{
    use AuthenticatesUsers, RegistersUsers {
        AuthenticatesUsers::redirectPath insteadof RegistersUsers;
    }
}
```

Now lastly, update `app/Http/Controllers/Auth/AuthController.php` and replace/update:

From:
```
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
```

To:
```
use App\Http\Controllers\Auth\AuthenticatesAndRegistersUsers;
```


To allow later direct redirection when somebody is already logged in, we need to add also some lines to ```app/Http/Middleware/RedirectIfAuthenticated.php```:
```
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

use KingStarter\LaravelSaml\Http\Traits\SamlAuth;

class RedirectIfAuthenticated
{
    use SamlAuth;
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // If authenticated with SAML Request, forward to SP
        if (Auth::check() && isset($request['SAMLRequest'])) { 
            $this->handleSamlLoginRequest($request);
        }

        if (Auth::guard($guard)->check()) {
            return redirect('/home');
        }
        return $next($request);
    }
}
```

## SAML Service Providers (SPs)

To add one or more service providers, go to the ```config/saml.php``` configuration file and scroll down to the 'sp' array. Having the Login-Address of the SAML-SP, add another entry. For reasons of internal interpretation, the URL needs to be Base64 encoded. 

### Debugging SP entries

In case that there are some problems receiving the Base64 string or evaluating SAML authentication requests in general, it is possible to use the debugger setting ```saml.debug_saml_request``` within the config file. 

```
    // Allow debugging within SamlAuth trait to get SP data  
    // during SAML authentication request
    'debug_saml_request' => true,
```

Make sure that the environmental logging variable ```APP_LOG_LEVEL``` is set to debug within your ```.env``` file.

## Credits

To awesome [kingstarter/laravel-saml](https://github.com/kingstarter/laravel-saml) package from which this package is derived.

## Issues

Please file issue at GitHub at (dorelljames/laravel-saml)[https://github.com/dorelljames/laravel-saml]
