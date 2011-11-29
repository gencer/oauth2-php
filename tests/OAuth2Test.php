<?php

use OAuth2\OAuth2;
use OAuth2\OAuth2ServerException;
use OAuth2\Model\IOAuth2AccessToken;
use OAuth2\Model\OAuth2AccessToken;
use OAuth2\Model\OAuth2AuthCode;
use OAuth2\Model\OAuth2Client;
use OAuth2\OAuth2StorageStub;
use OAuth2\OAuth2GrantCodeStub;
use OAuth2\OAuth2GrantUserStub;
use Symfony\Component\HttpFoundation\Request;

/**
 * OAuth2 test case.
 */
class OAuth2Test extends PHPUnit_Framework_TestCase {
  
  /**
   * @var OAuth2
   */
  private $fixture;
  
  /**
   * The actual token ID is irrelevant, so choose one:
   * @var string
   */
  private $tokenId = 'my_token';
  
  /**
   * Tests OAuth2->verifyAccessToken() with a missing token
   */
  public function testVerifyAccessTokenWithNoParam() {
    $mockStorage = $this->getMock('OAuth2\IOAuth2Storage');
    $this->fixture = new OAuth2($mockStorage);
    
    $scope = null;
    $this->setExpectedException('OAuth2\OAuth2AuthenticateException');
    $this->fixture->verifyAccessToken('', $scope);
  }
  
  /**
   * Tests OAuth2->verifyAccessToken() with a invalid token
   */
  public function testVerifyAccessTokenInvalidToken() {
    
    // Set up the mock storage to say this token does not exist
    $mockStorage = $this->getMock('OAuth2\IOAuth2Storage');
    $mockStorage->expects($this->once())
      ->method('getAccessToken')
      ->will($this->returnValue(false));
      
    $this->fixture = new OAuth2($mockStorage);
    
    $scope = null;
    $this->setExpectedException('OAuth2\OAuth2AuthenticateException');
    $this->fixture->verifyAccessToken($this->tokenId, $scope);
  }
  
  /**
   * Tests OAuth2->verifyAccessToken() with a malformed token
   * 
   * @dataProvider generateMalformedTokens
   */
  public function testVerifyAccessTokenMalformedToken(IOAuth2AccessToken $token) {
    
    // Set up the mock storage to say this token does not exist
    $mockStorage = $this->getMock('OAuth2\IOAuth2Storage');
    $mockStorage->expects($this->once())
      ->method('getAccessToken')
      ->will($this->returnValue($token));
      
    $this->fixture = new OAuth2($mockStorage);
    
    $scope = null;
    $this->setExpectedException('OAuth2\OAuth2AuthenticateException');
    $this->fixture->verifyAccessToken($this->tokenId, $scope);
  }
  
	/**
   * Tests OAuth2->verifyAccessToken() with different expiry dates
   * 
   * @dataProvider generateExpiryTokens
   */
  public function testVerifyAccessTokenCheckExpiry(IOAuth2AccessToken $token, $expectedToPass) {
    
    // Set up the mock storage to say this token does not exist
    $mockStorage = $this->getMock('OAuth2\IOAuth2Storage');
    $mockStorage->expects($this->once())
      ->method('getAccessToken')
      ->will($this->returnValue($token));
      
    $this->fixture = new OAuth2($mockStorage);
    
    $scope = null;
    
    
    // When valid, we just want any sort of token
    if ($expectedToPass) { 
      $actual = $this->fixture->verifyAccessToken($this->tokenId, $scope);
      $this->assertNotEmpty($actual, "verifyAccessToken() was expected to PASS, but it failed");
      $this->assertInstanceOf('OAuth2\Model\IOAuth2AccessToken', $actual);
    }
    else {
      $this->setExpectedException('OAuth2\OAuth2AuthenticateException');
      $this->fixture->verifyAccessToken($this->tokenId, $scope);
    }
  }
  
	/**
   * Tests OAuth2->verifyAccessToken() with different scopes
   * 
   * @dataProvider generateScopes
   */
  public function testVerifyAccessTokenCheckScope($scopeRequired, IOAuth2AccessToken $token, $expectedToPass) {
    
    // Set up the mock storage to say this token does not exist
    $mockStorage = $this->getMock('OAuth2\IOAuth2Storage');
    $mockStorage->expects($this->once())
      ->method('getAccessToken')
      ->will($this->returnValue($token));
      
    $this->fixture = new OAuth2($mockStorage);
    
    // When valid, we just want any sort of token
    if ($expectedToPass) {
      $actual = $this->fixture->verifyAccessToken($this->tokenId, $scopeRequired);
      $this->assertNotEmpty($actual, "verifyAccessToken() was expected to PASS, but it failed");
      $this->assertInstanceOf('OAuth2\Model\IOAuth2AccessToken', $actual);
    }
    else {
      $this->setExpectedException('OAuth2\OAuth2AuthenticateException');
      $this->fixture->verifyAccessToken($this->tokenId, $scopeRequired);
    }
  }
  
  /**
   * Tests OAuth2->grantAccessToken() for missing data
   * 
   * @dataProvider generateEmptyDataForGrant
   */
  public function testGrantAccessTokenMissingData($request) {
    $mockStorage = $this->getMock('OAuth2\IOAuth2Storage');
    $this->fixture = new OAuth2($mockStorage);
    
    $this->setExpectedException('OAuth2\OAuth2ServerException');
    $this->fixture->grantAccessToken($request);
  }
  
  /**
   * Tests OAuth2->grantAccessToken()
   * 
   * Tests the different ways client credentials can be provided.
   */
  public function testGrantAccessTokenCheckClientCredentials() {
    $mockStorage = $this->getMock('OAuth2\IOAuth2Storage');
    $mockStorage->expects($this->any())
      ->method('getClient')
      ->will($this->returnValue(new OAuth2Client('dev-abc')));
    $mockStorage->expects($this->any())
      ->method('checkClientCredentials')
      ->will($this->returnValue(TRUE)); // Always return true for any combination of user/pass
    $this->fixture = new OAuth2($mockStorage);
    
    $inputData = array('grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE);
    $request = $this->createRequest($inputData);
    
    // First, confirm that an non-client related error is thrown:
    try {
      $this->fixture->grantAccessToken($request);
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch ( OAuth2ServerException $e ) {
      $this->assertEquals(OAuth2::ERROR_INVALID_CLIENT, $e->getMessage());
    }

    // Confirm Auth header
    $authHeaders = array('PHP_AUTH_USER' => 'dev-abc', 'PHP_AUTH_PW' => 'pass');
    $inputData = array('grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE, 'client_id' => 'dev-abc'); // When using auth, client_id must match
    $request = $this->createRequest($inputData, $authHeaders);
    try {
      $this->fixture->grantAccessToken($request);
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch ( OAuth2ServerException $e ) {
      $this->assertNotEquals(OAuth2::ERROR_INVALID_CLIENT, $e->getMessage());
    }
    
    // Confirm GET/POST
    $authHeaders = array();
    $inputData = array('grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE, 'client_id' => 'dev-abc', 'client_secret' => 'foo'); // When using auth, client_id must match
    $request = $this->createRequest($inputData, $authHeaders);
    try {
      $this->fixture->grantAccessToken($request);
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch ( OAuth2ServerException $e ) {
      $this->assertNotEquals(OAuth2::ERROR_INVALID_CLIENT, $e->getMessage());
    }
  }
  
  /**
   * Tests OAuth2->grantAccessToken() with Auth code grant
   * 
   */
  public function testGrantAccessTokenWithGrantAuthCodeMandatoryParams() {
    $mockStorage = $this->createBaseMock('OAuth2\IOAuth2GrantCode');
    $mockStorage->expects($this->any())
      ->method('getClient')
      ->will($this->returnValue(new OAuth2Client('dev-abc')));
    $mockStorage->expects($this->any())
      ->method('checkClientCredentials')
      ->will($this->returnValue(TRUE)); // Always return true for any combination of user/pass

    $inputData = array('grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE, 'client_id' => 'a', 'client_secret' => 'b');
    $fakeAuthCode = array('client_id' => $inputData['client_id'], 'redirect_uri' => '/foo', 'expires' => time() + 60);
    $fakeAccessToken = array('access_token' => 'abcde');
    
    // Ensure redirect URI and auth-code is mandatory
    try {
      $this->fixture = new OAuth2($mockStorage);
      $this->fixture->setVariable(OAuth2::CONFIG_ENFORCE_INPUT_REDIRECT, true); // Only required when this is set
      $request = $this->createRequest($inputData + array('code' => 'foo'));
      $this->fixture->grantAccessToken($request);
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch ( OAuth2ServerException $e ) {
      $this->assertEquals(OAuth2::ERROR_INVALID_REQUEST, $e->getMessage());
    }
    try {
      $this->fixture = new OAuth2($mockStorage);
      $request = $this->createRequest($inputData + array('redirect_uri' => 'foo'));
      $this->fixture->grantAccessToken($request);
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch ( OAuth2ServerException $e ) {
      $this->assertEquals(OAuth2::ERROR_INVALID_REQUEST, $e->getMessage());
    }
  }
  
   /**
   * Tests OAuth2->grantAccessToken() with Auth code grant
   * 
   */
  public function testGrantAccessTokenWithGrantAuthCodeNoToken() {
    $mockStorage = $this->createBaseMock('OAuth2\IOAuth2GrantCode');
    $mockStorage->expects($this->any())
      ->method('getClient')
      ->will($this->returnValue(new OAuth2Client('dev-abc')));
    $mockStorage->expects($this->any())
      ->method('checkClientCredentials')
      ->will($this->returnValue(TRUE)); // Always return true for any combination of user/pass

    $inputData = array('grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE, 'client_id' => 'a', 'client_secret' => 'b', 'redirect_uri' => 'foo', 'code'=> 'foo');
    
    // Ensure missing auth code raises an error
    try {
      $this->fixture = new OAuth2($mockStorage);
      $request = $this->createRequest($inputData);
      $this->fixture->grantAccessToken($request);
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    }
    catch ( OAuth2ServerException $e ) {
      $this->assertEquals(OAuth2::ERROR_INVALID_GRANT, $e->getMessage());
    }
  }
  
  /**
   * Tests OAuth2->grantAccessToken() with checks the redirect URI
   * 
   */
  public function testGrantAccessTokenWithGrantAuthCodeRedirectChecked() {
    $inputData = array('redirect_uri' => 'http://www.crossdomain.com/my/subdir', 'grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE, 'client_id' => 'my_little_app', 'client_secret' => 'b', 'code'=> 'foo');
    $storedToken = new OAuth2AuthCode('my_little_app', '', time() + 60, NULL, NULL, 'http://www.example.com');
    
    $mockStorage = $this->createBaseMock('Oauth2\IOAuth2GrantCode');
    $mockStorage->expects($this->any())
      ->method('getClient')
      ->will($this->returnValue(new OAuth2Client('my_little_app')));
    $mockStorage->expects($this->any())
      ->method('checkClientCredentials')
      ->will($this->returnValue(TRUE)); // Always return true for any combination of user/pass
    $mockStorage->expects($this->any())
      ->method('getAuthCode')
      ->will($this->returnValue($storedToken));
      
    // Ensure that the redirect_uri is checked
    try {
      $this->fixture = new OAuth2($mockStorage);
      $request = $this->createRequest($inputData);
      $this->fixture->grantAccessToken($request);
      
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    }
    catch ( OAuth2ServerException $e ) {
      $this->assertEquals(OAuth2::ERROR_REDIRECT_URI_MISMATCH, $e->getMessage());
    }
  }
  
	/**
   * Tests OAuth2->grantAccessToken() with checks the client ID is matched
   * 
   */
  public function testGrantAccessTokenWithGrantAuthCodeClientIdChecked() {
    $inputData = array('client_id' => 'another_app', 'grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE, 'redirect_uri' => 'http://www.example.com/my/subdir', 'client_secret' => 'b', 'code'=> 'foo');
    $storedToken = new OAuth2AuthCode('my_little_app', '', time() + 60, NULL, NULL, 'http://www.example.com');
    
    $mockStorage = $this->createBaseMock('OAuth2\IOAuth2GrantCode');
    $mockStorage->expects($this->any())
      ->method('getClient')
      ->will($this->returnValue(new OAuth2Client('x')));
    $mockStorage->expects($this->any())
      ->method('checkClientCredentials')
      ->will($this->returnValue(TRUE)); // Always return true for any combination of user/pass
    $mockStorage->expects($this->any())
      ->method('getAuthCode')
      ->will($this->returnValue($storedToken));
      
    // Ensure the client ID is checked
    try {
      $this->fixture = new OAuth2($mockStorage);
      $request = $this->createRequest($inputData);
      $this->fixture->grantAccessToken($request);
      
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    }
    catch ( OAuth2ServerException $e ) {
      $this->assertEquals(OAuth2::ERROR_INVALID_GRANT, $e->getMessage());
    }
  }
  
  /**
   * Tests OAuth2->grantAccessToken() with implicit
   * 
   */
  public function testGrantAccessTokenWithGrantImplicit() {
    $this->markTestIncomplete ( "grantAccessToken test not implemented" );
    
    $this->fixture->grantAccessToken(/* parameters */);
  }
  
	/**
   * Tests OAuth2->grantAccessToken() with user credentials
   * 
   */
  public function testGrantAccessTokenWithGrantUser() {

    $data = new \stdClass;

    $stub = new OAuth2GrantUserStub;
    $stub->addClient(new OAuth2Client('cid', 'cpass'));
    $stub->addUser('foo', 'bar', null, $data);
    $stub->setAllowedGrantTypes(array('authorization_code', 'password'));

    $oauth2 = new OAuth2($stub);

    $response = $oauth2->grantAccessToken(new Request(array(
      'grant_type' => 'password',
      'client_id' => 'cid',
      'client_secret' => 'cpass',
      'username' => 'foo',
      'password' => 'bar',
    )));

    $this->assertSame(array(
      'content-type' => array('application/json;charset=UTF-8'),
      'cache-control' => array('no-store, private'),
      'pragma' => array('no-cache'),
    ), array_diff_key(
      $response->headers->all(), 
      array('date' => null)
    ));

    $this->assertRegExp('{"access_token":"[^"]+","expires_in":3600,"token_type":"bearer","scope":null}', $response->getContent());

    $token = $stub->getLastAccessToken();
    $this->assertSame('cid', $token->getClientId());
    $this->assertSame($data, $token->getData());
    $this->assertSame(null, $token->getScope());
  }

  public function testGrantAccessTokenWithGrantUserWithAddScopeThrowsError() {

    $stub = new OAuth2GrantUserStub;
    $stub->addClient(new OAuth2Client('cid', 'cpass'));
    $stub->addUser('foo', 'bar');
    $stub->setAllowedGrantTypes(array('authorization_code', 'password'));

    $oauth2 = new OAuth2($stub);

    try {
      $response = $oauth2->grantAccessToken(new Request(array(
        'grant_type' => 'password',
        'client_id' => 'cid',
        'client_secret' => 'cpass',
        'username' => 'foo',
        'password' => 'bar',
        'scope' => 'scope1 scope2',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch(OAuth2ServerException $e) {
      $this->assertSame('invalid_scope', $e->getMessage());
      $this->assertSame(array(
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-store',
        'Pragma' => 'no-cache',
      ), $e->getResponseHeaders());
      $this->assertSame('{"error":"invalid_scope"}', $e->getResponseBody());
    }
  }
  
  public function testGrantAccessTokenWithGrantUserWithScope() {

    $stub = new OAuth2GrantUserStub;
    $stub->addClient(new OAuth2Client('cid', 'cpass'));
    $stub->addUser('foo', 'bar', 'scope1 scope2');
    $stub->setAllowedGrantTypes(array('authorization_code', 'password'));

    $oauth2 = new OAuth2($stub);

    $response = $oauth2->grantAccessToken(new Request(array(
      'grant_type' => 'password',
      'client_id' => 'cid',
      'client_secret' => 'cpass',
      'username' => 'foo',
      'password' => 'bar',
      'scope' => 'scope1 scope2',
    )));

    $this->assertSame(array(
      'content-type' => array('application/json;charset=UTF-8'),
      'cache-control' => array('no-store, private'),
      'pragma' => array('no-cache'),
    ), array_diff_key(
      $response->headers->all(), 
      array('date' => null)
    ));

    $this->assertRegExp('{"access_token":"[^"]+","expires_in":3600,"token_type":"bearer","scope":"scope1 scope2"}', $response->getContent());

    $token = $stub->getLastAccessToken();
    $this->assertSame('cid', $token->getClientId());
    $this->assertSame('scope1 scope2', $token->getScope());
  }
  
  public function testGrantAccessTokenWithGrantUserWithReducedScope() {

    $stub = new OAuth2GrantUserStub;
    $stub->addClient(new OAuth2Client('cid', 'cpass'));
    $stub->addUser('foo', 'bar', 'scope1 scope2');
    $stub->setAllowedGrantTypes(array('authorization_code', 'password'));

    $oauth2 = new OAuth2($stub);

    $response = $oauth2->grantAccessToken(new Request(array(
      'grant_type' => 'password',
      'client_id' => 'cid',
      'client_secret' => 'cpass',
      'username' => 'foo',
      'password' => 'bar',
      'scope' => 'scope1',
    )));

    $this->assertSame(array(
      'content-type' => array('application/json;charset=UTF-8'),
      'cache-control' => array('no-store, private'),
      'pragma' => array('no-cache'),
    ), array_diff_key(
      $response->headers->all(), 
      array('date' => null)
    ));

    $this->assertRegExp('{"access_token":"[^"]+","expires_in":3600,"token_type":"bearer","scope":"scope1 scope2"}', $response->getContent());

    $token = $stub->getLastAccessToken();
    $this->assertSame('cid', $token->getClientId());
    $this->assertSame('scope1 scope2', $token->getScope());
  }

	/**
   * Tests OAuth2->grantAccessToken() with client credentials
   * 
   */
  public function testGrantAccessTokenWithGrantClient() {
    $this->markTestIncomplete ( "grantAccessToken test not implemented" );
    
    $this->fixture->grantAccessToken(/* parameters */);
  }
  
	/**
   * Tests OAuth2->grantAccessToken() with refresh token
   * 
   */
  public function testGrantAccessTokenWithGrantRefresh() {
    $this->markTestIncomplete ( "grantAccessToken test not implemented" );
    
    $this->fixture->grantAccessToken(/* parameters */);
  }
  
	/**
   * Tests OAuth2->grantAccessToken() with extension
   * 
   */
  public function testGrantAccessTokenWithGrantExtension() {
    $this->markTestIncomplete ( "grantAccessToken test not implemented" );
    
    $this->fixture->grantAccessToken(/* parameters */);
  }
  
  /**
   * Tests OAuth2->getAuthorizeParams()
   */
  public function testGetAuthorizeParams() {
    // TODO Auto-generated OAuth2Test->testGetAuthorizeParams()
    $this->markTestIncomplete ( "getAuthorizeParams test not implemented" );
    
    $this->fixture->getAuthorizeParams(/* parameters */);
  
  }
  
  /**
   * Tests OAuth2->finishClientAuthorization()
   */
  public function testFinishClientAuthorization() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
        'client_id' => 'blah',
        'redirect_uri' => 'http://www.example.com/?foo=bar',
        'response_type' => 'code',
        'state' => '42',
    )));

    $this->assertSame(302, $response->getStatusCode());
    $this->assertRegexp('#^http://www\.example\.com/\?foo=bar&state=42&code=#', $response->headers->get('location'));

    $code = $stub->getLastAuthCode();
    $this->assertSame('blah', $code->getClientId());
    $this->assertSame(null, $code->getScope());
    $this->assertSame($data, $code->getData());
  }

  public function testFinishClientAuthorizationThrowsErrorIfClientIdMissing() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {  
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
        'redirect_uri' => 'http://www.example.com/?foo=bar',
        'response_type' => 'code',
        'state' => '42',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('invalid_request', $e->getMessage());
      $this->assertSame('No client id supplied', $e->getDescription());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfClientUnkown() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
        'client_id' => 'foo',
        'redirect_uri' => 'http://www.example.com/?foo=bar',
        'response_type' => 'code',
        'state' => '42',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('invalid_client', $e->getMessage());
      $this->assertSame('Unknown client', $e->getDescription());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfNoAvailUri() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array()));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
        'client_id' => 'blah',
        'response_type' => 'code',
        'state' => '42',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('redirect_uri_mismatch', $e->getMessage());
      $this->assertSame('No redirect URL was supplied or registered.', $e->getDescription());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfMoreThanOneRegisterdUriAndNoSupplied() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://a.example.com', 'http://b.example.com')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
        'client_id' => 'blah',
        'response_type' => 'code',
        'state' => '42',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('redirect_uri_mismatch', $e->getMessage());
      $this->assertSame('No redirect URL was supplied and more than one is registered.', $e->getDescription());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfNoSuppliedUri() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://a.example.com')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
        'client_id' => 'blah',
        'response_type' => 'code',
        'state' => '42',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('redirect_uri_mismatch', $e->getMessage());
      $this->assertSame('The redirect URI is mandatory and was not supplied.', $e->getDescription());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfNoMatchingUri() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://a.example.com')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
        'client_id' => 'blah',
        'response_type' => 'code',
        'state' => '42',
        'redirect_uri' => 'http://www.example.com/',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('redirect_uri_mismatch', $e->getMessage());
      $this->assertSame('The redirect URI provided does not match registered URI(s).', $e->getDescription());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfResponseTypeIsMissing() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
          'client_id' => 'blah',
          'redirect_uri' => 'http://www.example.com/?foo=bar',
          'state' => '42',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('invalid_request', $e->getMessage());
      $this->assertSame('Invalid response type.', $e->getDescription());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfResponseTypeNotSupported() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
          'client_id' => 'blah',
          'redirect_uri' => 'http://www.example.com/?foo=bar',
          'state' => '42',
          'response_type' => 'token',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('unsupported_response_type', $e->getMessage());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfResponseTypeUnknown() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
          'client_id' => 'blah',
          'redirect_uri' => 'http://www.example.com/?foo=bar',
          'state' => '42',
          'response_type' => 'foo',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('unsupported_response_type', $e->getMessage());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfScopeUnkown() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(true, $data, new Request(array(
          'client_id' => 'blah',
          'redirect_uri' => 'http://www.example.com/?foo=bar',
          'state' => '42',
          'response_type' => 'code',
          'scope' => 'x',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('invalid_scope', $e->getMessage());
    }
  }

  public function testFinishClientAuthorizationThrowsErrorIfUnauthorized() {

    $stub = new OAuth2GrantCodeStub;
    $stub->addClient(new OAuth2Client('blah', 'foo', array('http://www.example.com/')));
    $oauth2 = new OAuth2($stub);

    $data = new \stdClass;

    try {
      $response = $oauth2->finishClientAuthorization(false, $data, new Request(array(
          'client_id' => 'blah',
          'redirect_uri' => 'http://www.example.com/?foo=bar',
          'state' => '42',
          'response_type' => 'code',
      )));
      $this->fail('The expected exception OAuth2ServerException was not thrown');
    } catch (OAuth2ServerException $e) {
      $this->assertSame('access_denied', $e->getMessage());
      $this->assertSame('The user denied access to your application', $e->getDescription());
      $this->assertSame(array(
        'Location' => 'http://www.example.com/?foo=bar&error=access_denied&error_description=The+user+denied+access+to+your+application&state=42',
      ), $e->getResponseHeaders());
    }
  }

  /**
   * @dataProvider getTestGetBearerTokenData
   */
  public function testGetBearerToken(Request $request, $token, $remove = false, $exception = null, $exceptionMessage = null, $headers = null, $body = null) {
    $mock = $this->getMock('OAuth2\IOAuth2Storage');
    $oauth2 = new OAuth2($mock);

    try {
      $this->assertSame($token, $oauth2->getBearerToken($request, $remove));
      if ($exception) {
        $this->fail('The expected exception OAuth2ServerException was not thrown');
      }
      if ($remove) {
        $this->assertNull($request->headers->get('AUTHORIZATION'));
        $this->assertNull($request->query->get('access_token'));
        $this->assertNull($request->request->get('access_token'));
      }
    } catch(\Exception $e) {
      if (!$exception || !($e instanceof $exception)) {
        throw $e;
      }
      $this->assertSame($headers, $e->getResponseHeaders());
      $this->assertSame($body, $e->getResponseBody());
    }
  }

  public function getTestGetBearerTokenData() {

    $data = array();

    // Authorization header
    $request = new Request;
    $request->headers->set('AUTHORIZATION', 'Bearer foo');
    $data[] = array($request, 'foo');

    // Authorization header with remove
    $request = new Request;
    $request->headers->set('AUTHORIZATION', 'Bearer foo');
    $data[] = array($request, 'foo', true);

    // GET
    $data[] = array(new Request(array('access_token' => 'foo')), 'foo');

    // GET with remove
    $data[] = array(new Request(array('access_token' => 'foo')), 'foo', true);

    // POST
    $request = new Request;
    $request->setMethod('POST');
    $request->server->set('CONTENT_TYPE', 'application/x-www-form-urlencoded');
    $request->request->set('access_token', 'foo');
    $data[] = array($request, 'foo');

    // POST with remove
    $request = new Request;
    $request->setMethod('POST');
    $request->server->set('CONTENT_TYPE', 'application/x-www-form-urlencoded');
    $request->request->set('access_token', 'foo');
    $data[] = array($request, 'foo', true);

    // No access token provided returns NULL
    $data[] = array(new Request, NULL);

    // More than one method throws exception
    $request = new Request(array('access_token' => 'foo'));
    $request->headers->set('AUTHORIZATION', 'Bearer foo');
    $data[] = array(
      $request,
      null,
      null,
      'OAuth2\OAuth2ServerException',
      'invalid_request',
      array(
        'WWW-Authenticate' => 'Bearer realm="Service", error="invalid_request", error_description="Only one method may be used to authenticate at a time (Auth header, GET or POST)."',
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-store',
        'Pragma' => 'no-cache',
      ),
      '{"error":"invalid_request","error_description":"Only one method may be used to authenticate at a time (Auth header, GET or POST)."}'
    );

    // POST with incorrect Content-Type ignores POST vars
    $request = new Request;
    $request->setMethod('POST');
    $request->server->set('CONTENT_TYPE', 'multipart/form-data');
    $request->request->set('access_token', 'foo');
    $data[] = array(
      $request,
      null,
      false,
    );

    return $data;
  }

  // Utility methods
  
  /**
   * 
   * @param string $interfaceName
   */
  protected function createBaseMock($interfaceName) {
    $mockStorage = $this->getMock($interfaceName);
    $mockStorage->expects($this->any())
      ->method('checkClientCredentials')
      ->will($this->returnValue(TRUE)); // Always return true for any combination of user/pass
    $mockStorage->expects($this->any())
      ->method('checkRestrictedGrantType')
      ->will($this->returnValue(TRUE)); // Always return true for any combination of user/pass
      
     return $mockStorage;
  }
  
  // Data Providers below:
  
  /**
   * Dataprovider for testVerifyAccessTokenMalformedToken().
   * 
   * Produces malformed access tokens
   */
  public function generateMalformedTokens() {
    return array(
      array(new OAuth2AccessToken(NULL, NULL, NULL)),
    );
  }
  
  /**
   * Dataprovider for testVerifyAccessTokenCheckExpiry().
   * 
   * Produces malformed access tokens
   */
  public function generateExpiryTokens() {
    return array(
      array(new OAuth2AccessToken('blah', '', time() - 30),                 FALSE), // 30 seconds ago should fail
      array(new OAuth2AccessToken('blah', '', time() - 1),                  FALSE), // now-ish should fail
      array(new OAuth2AccessToken('blah', '', 0),                           FALSE), // 1970 should fail
      array(new OAuth2AccessToken('blah', '', time() + 30),                 TRUE),  // 30 seconds in the future should be valid
      array(new OAuth2AccessToken('blah', '', time() + 86400),              TRUE),  // 1 day in the future should be valid
      array(new OAuth2AccessToken('blah', '', time() + (365 * 86400)),      TRUE),  // 1 year should be valid
      array(new OAuth2AccessToken('blah', '', time() + (10 * 365 * 86400)), TRUE),  // 10 years should be valid
    );
  }
  
  /**
   * Dataprovider for testVerifyAccessTokenCheckExpiry().
   * 
   * Produces malformed access tokens
   */
  public function generateScopes() {
    $baseToken = array('client_id' => 'blah', 'expires' => time() + 60);

    $token = function($scope) {
      return new OAuth2AccessToken('blah', '', time() + 60, $scope);
    };
    
    return array(
      array(null,   $token(null),                TRUE), // null scope is valid
      array('',     $token(''),                  TRUE), // empty scope is valid
      array('read', $token('read'),              TRUE), // exact same scope is valid
      array('read', $token(' read '),            TRUE), // exact same scope is valid
      array(' read ', $token('read'),            TRUE), // exact same scope is valid
      array('read', $token('read write delete'), TRUE), // contains scope 
      array('read', $token('write read delete'), TRUE), // contains scope 
      array('read', $token('delete write read'), TRUE), // contains scope
      
      // Invalid combinations
      array('read', $token('write'),             FALSE),
      array('read', $token('apple banana'),      FALSE),
      array('read', $token('apple read-write'),  FALSE),
      array('read', $token('apple read,write'),  FALSE),
      array('read', $token(null),                FALSE),
      array('read', $token(''),                  FALSE),
    );
  }
  
  /**
   * Provider for OAuth2->grantAccessToken()
   */
  public function generateEmptyDataForGrant() {
    return array(
      array(
        $this->createRequest(array(), array())
      ),
      array(
        $this->createRequest(array(), array('grant_type' => OAuth2::GRANT_TYPE_AUTH_CODE)) // grant_type in auth headers should be ignored
      ),
      array(
        $this->createRequest(array('not_grant_type' => 5), array())
      ),
    );
  }

  public function createRequest(array $query = array(), array $headers = array()) {
    $request = new Request(
      $query      // _GET
      , array()   // _REQUEST
      , array()   // attributes
      , array()   // _COOKIES
      , array()   // _FILES
      , $headers  // _SERVER
    );
    return $request;
  }
}

