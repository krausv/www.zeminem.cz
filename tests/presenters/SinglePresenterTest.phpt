<?php

namespace Test;

use Model;
use Nette;
use Tester;

require __DIR__ . '/../bootstrap.php';

/**
 * @testCase
 */
class SinglePresenterTest extends \CustomTestCase
{

	/** @var Model\Posts */
	private $posts;

	public function __construct()
	{
		$this->openPresenter('Single:');
		$this->posts = $this->getService('Model\Posts');
	}

	public function testRenderAbout()
	{
		$this->checkAction('about');
	}

	/** @dataProvider dataArticles */
	public function testRenderArticles($slug)
	{
		$this->checkAction('article', ['slug' => $slug]);
	}

	public function testRedirectEmptyArticle()
	{
		$this->checkRedirect('article', '/');
	}

	public function testNonWebalizedArticle()
	{
		$this->checkRedirect('article', '/rcs-rcs-5', ['slug' => 'rčš .rčš 5']);
	}

	public function testForward()
	{
		/** @var Nette\Application\Responses\ForwardResponse $response */
		$response = $this->check('article', ['slug' => 'about']);
		Tester\Assert::true($response instanceof Nette\Application\Responses\ForwardResponse);
		//TODO: checkForward method in testbench...
	}

	public function testRenderDevelop()
	{
		$this->checkAction('develop');
	}

	public function testRenderObsah()
	{
		$this->checkAction('obsah');
	}

	public function testRenderReference()
	{
		$this->checkAction('reference');
	}

	///// dataProviders /////

	/**
	 * @return array of arrays
	 */
	public function dataArticles()
	{
		$articles = $this->posts->findBy([], NULL, 10, 0);
		//$articles = $this->posts->findBy(array());
		//$articles = $this->posts->findOneBy(array());
		$data = [];
		foreach ($articles as $article) {
			$data[] = [$article->slug];
		}
		return $data;
	}

}

(new SinglePresenterTest)->run();
