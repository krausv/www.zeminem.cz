<?php

namespace Model;

use Nette;

/**
 * TODO: oddělit TAG logiku do samostatné třídy
 * Class Posts
 * @package Model
 */
class Posts extends Nette\Object {

	protected $tableName = 'posts';

	/** @var \Nette\Database\Context */
	private $database;

	public function __construct(Nette\Database\Context $context) {
		$this->database = $context;
	}

	/**
	 * @param $title
	 * @param $slug
	 * @param $tags
	 * @param $body
	 * @param $release
	 */
	public function newPost($title, $slug, $tags, $body, $release) {
		$data = array(
			'title' => $title,
			'slug' => Nette\Utils\Strings::webalize($slug),
			'body' => $body,
			'date' => new \DateTime(),
			'release_date' => $release,
		);
		$post = $this->database->table($this->tableName)->insert($data); //Save post
		//Save tags:
		if (!empty($tags[0])) {
			foreach ($tags as $tag) {
				$color = substr(md5(rand()), 0, 6); //Short and sweet
				if (count($this->getTagByName($tag)) == 0) { //Pouze nové tagy!
					$tag = $this->database->table('tags')->insert(array('name' => $tag, 'color' => $color));
					$this->database->table('posts_tags')->insert(array('post_id' => $post->id, 'tag_id' => $tag->id));
				} else {
					$tag = $this->getTagByName($tag)->fetch();
					$this->database->table('posts_tags')->insert(array('post_id' => $post->id, 'tag_id' => $tag->id));
				}
			}
		}
	}

	/**
	 * @param $title
	 * @param $slug
	 * @param $tags
	 * @param $body
	 * @param $release
	 * @param $id
	 */
	public function updatePost($title, $slug, $tags, $body, $release, $id) {
		$data = array(
			'title' => $title,
			'slug' => Nette\Utils\Strings::webalize($slug),
			'body' => $body,
			'release_date' => $release,
		);
		//Update post:
		$post = $this->database->table('posts')->where('id = ?', $id)->update($data);
		if (!empty($tags[0])) {
			$this->database->table('posts_tags')->where('post_id = ?', $id)->delete();
			foreach ($tags as $tag) {
				$color = substr(md5(rand()), 0, 6); //Short and sweet
				$tmp = $this->getTagByName($tag);
				if (count($tmp) == 0) { //Pouze nové tagy!
					$tag = $this->database->table('tags')->insert(array('name' => $tag, 'color' => $color));
					$this->database->table('posts_tags')->insert(array('post_id' => $id, 'tag_id' => $tag->id));
				} else {
					$tmp = $this->getTagByName($tag)->fetch(); //Again (because there is - maybe - one new tag)
					$this->database->table('posts_tags')->insert(array('post_id' => $id, 'tag_id' => $tmp->id));
				}
			}
		} else {
			$this->database->table('posts_tags')->where('post_id = ?', $id)->delete();
		}
	}

	/**
	 * @return Nette\Database\Table\Selection
	 */
	public function getAllPosts() {
		return $this->database->table('posts')->where('release_date < NOW()');
	}

	/**
	 * @param $limit
	 * @param $offset
	 * @return Nette\Database\Table\Selection
	 */
	public function getPosts($limit, $offset) {
		return $this->database->table('posts')->where('release_date < NOW()')->limit($limit, $offset)->order('date DESC');
	}

	/**
	 * @param $id
	 * @return bool|mixed|IRow
	 */
	public function getPostByID($id) {
		return $this->database->table('posts')->where('release_date < NOW()')->where('id = ?', $id)->fetch();
	}

	/**
	 * TODO: optimalizovat
	 * @param $tag_id
	 * @param int $limit
	 * @return array
	 */
	public function getPostsByTagID($tag_id, $limit = 20) {
		$array = array();
		foreach ($this->database->table('posts_tags')->where('tag_id = ?', $tag_id) as $post_tag) {
			$array[] = $this->getPostByID($post_tag->post_id);
		}
		return array_reverse($array);
	}

	/**
	 * @param $tag_id
	 * @return bool|mixed|IRow
	 */
	public function getTagByID($tag_id) {
		return $this->database->table('tags')->where('id = ?', $tag_id)->fetch();
	}

	/**
	 * @param $post_id
	 * @return Nette\Database\Table\Selection
	 */
	public function getTagsByPostID($post_id) {
		return $this->database->table('posts_tags')->where('post_id = ?', $post_id);
	}

	/**
	 * @param $name
	 * @return Nette\Database\Table\Selection
	 */
	public function getTagByName($name) {
		return $this->database->table('tags')->where('name = ?', $name);
	}

	/**
	 * @return Nette\Database\Table\Selection
	 */
	public function getAllTags() {
		return $this->database->table('tags')->order('name');
	}

	/**
	 * @param $tag_id
	 * @param $data
	 */
	public function updateTagByID($tag_id, $data) {
		$this->database->table('tags')->where('id = ?', $tag_id)->update($data);
	}

	/**
	 * @param $id
	 */
	public function deletePostByID($id) {
		//Delete relation:
		$this->database->table('posts_tags')->where('post_id = ?', $id)->delete();
		//Delete tags:
		// zatím je tam nechávám...
		//Delete post:
		$this->database->table('posts')->where('id = ?', $id)->delete();
	}

	/**
	 * @param $tag_id
	 */
	public function deleteTagById($tag_id) {
		//Delete relation:
		$this->database->table('posts_tags')->where('tag_id = ?', $tag_id)->delete();
		//Delete tag:
		$this->database->table('tags')->where('id = ?', $tag_id)->delete();
	}

	/**
	 * @param $search string
	 * @return Nette\Database\Table\Selection
	 */
	public function fulltextSearch($search) {
		$where = "";
		$ft_min_word_len = 4;
		preg_match_all("~[\\pL\\pN_]+('[\\pL\\pN_]+)*~u", stripslashes($search), $matches);
		foreach ($matches[0] as $part) {
			if (iconv_strlen($part, "utf-8") < $ft_min_word_len) {
				$accents = array('aá', 'cč', 'eéě', 'ií', 'oó', 'rř', 'sš', 'uúů', 'zž');
				foreach ($accents as $accent) {
					$part = preg_replace("<[$accent]>iu", "[$accent]+", $part);
				}
				$regexp = "REGEXP '[[:<:]]" . addslashes(mb_strtoupper($part, 'UTF-8')) . "[[:>:]]'";
				$where .= " OR (title $regexp OR body $regexp)";
			}
		}

		//TODO: tag search
		//$where .= " OR tag LIKE $search";

		return $this->database->table('mirror_posts') //MATCH is case insensitive
			->where("MATCH(title, body) AGAINST (? IN BOOLEAN MODE)$where", $search . '*')
			->order("5 * MATCH(title) AGAINST (?) + MATCH(body) AGAINST (?) DESC", $search, $search)
			->limit(50);
	}

	// Routers:
	public function getBySlug($slug) {
		return $this->database->table('posts')->where('slug = ?', $slug);
	}

}