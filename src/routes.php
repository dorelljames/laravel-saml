<?php

/**
 * The laravel-saml package route configuration
 */

Route::group([
    'namespace' => '\KingStarter\LaravelSaml\Http\Controllers'
  ], function () {
    Route::get('/saml/idp/metadata', ['uses' => 'SamlIdpController@metadata', 'as' => 'saml.idp.metadata']);
  }
);
