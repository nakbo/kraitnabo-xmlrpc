<?php
/** @noinspection DuplicatedCode */
/** @noinspection PhpUndefinedFunctionInspection */
/** @noinspection PhpUnused */
/** @noinspection PhpRedundantCatchClauseInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection PhpUndefinedFieldInspection */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
/**
 * Typecho Blog Platform
 *
 * @copyright  Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license    GNU General Public License 2.0
 * @version    $Id$
 */

/**
 * XmlRpc接口
 *
 * @author Krait (https://github.com/kraity)
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Widget_XmlRpc extends Widget_Abstract_Contents implements Widget_Interface_Do
{

    /**
     * uid
     * @var int
     */
    private $uid;

    /**
     * 当前错误
     *
     * @access private
     * @var IXR_Error
     */
    private $error;

    /**
     * 已经使用过的组件列表
     *
     * @access private
     * @var array
     */
    private $_usedWidgetNameList = array();

    /**
     * 代理工厂方法,将类静态化放置到列表中
     *
     * @access public
     * @param string $alias 组件别名
     * @param mixed $params 传递的参数
     * @param mixed $request 前端参数
     * @param boolean $enableResponse 是否允许http回执
     * @return object
     * @throws Typecho_Exception
     */
    private function singletonWidget($alias, $params = NULL, $request = NULL, $enableResponse = true)
    {
        $this->_usedWidgetNameList[] = $alias;
        return Typecho_Widget::widget($alias, $params, $request, $enableResponse);
    }

    /**
     * 如果这里没有重载, 每次都会被默认执行
     *
     * @access public
     * @param boolean $run 是否执行
     * @return void
     */
    public function execute($run = false)
    {
        if ($run) {
            parent::execute();
        }
        // 临时保护模块
        $this->security->enable(false);
    }

    /**
     * XmlRpc清单
     * @param $v
     * @return array
     */
    public function NbGetManifest($v)
    {
        return Widget_XmlRpc::NbGetManifestStatic();
    }

    /**
     * 静态清单
     * @return array
     */
    public static function NbGetManifestStatic()
    {
        return array(
            "engineName" => "typecho",
            "versionCode" => 15,
            "versionName" => "2.4"
        );
    }


    /**
     * 检查权限
     *
     * @access public
     * @param string $union
     * @param string $name
     * @param string $password
     * @param string $level
     * @return boolean
     * @throws Typecho_Widget_Exception
     */
    public function access($union, $name, $password, $level = 'contributor')
    {
        /** 唯一标识 */
        Typecho_Cookie::set('__typecho_xmlrpc_union', $union, 0);
        Typecho_Cookie::set('__typecho_xmlrpc_name', $name, 0);
        /** 登陆状态 */
        if (!$this->user->hasLogin()) {
            if ($this->user->login($name, $password, true)) {
                $this->uid = $this->user->uid;
                $this->user->execute();
            } else {
                $this->error = new IXR_Error(403, _t('无法登陆, 密码错误'));
                return false;
            }
        }
        /** 验证权限 */
        if ($this->user->pass($level, true)) {
            return true;
        } else {
            $this->error = new IXR_Error(403, _t('权限不足'));
            return false;
        }
    }

    /**
     * 获取用户
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Widget_Exception
     * @throws Typecho_Exception
     * @noinspection PhpUndefinedFieldInspection
     */
    public function NbGetUser($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        return array(true, array(
            'uid' => $this->user->uid,
            'name' => $this->user->name,
            'mail' => $this->user->mail,
            'screenName' => $this->user->screenName,
            'url' => $this->user->url,
            'created' => $this->user->created,
            'activated' => $this->user->activated,
            'logged' => $this->user->logged,
            'group' => $this->user->group,
            'authCode' => $this->user->authCode
        ));
    }

    /**
     * markdown
     * @param string $text
     * @return string
     */
    public function commonParseMarkdown($text)
    {
        /** 处理Markdown **/
        $isMarkdown = (0 === strpos($text, '<!--markdown-->'));
        return $isMarkdown ? substr($text, 15) : $text;
    }

    /**
     * 获取内容摘要
     * @param String $markdown
     * @return string
     */
    public function commonParseDescription($markdown)
    {
        /**  获取文章内容摘要 **/
        try {
            $description = strip_tags($this->singletonWidget('Widget_Abstract_Contents')->markdown($markdown));
            return Typecho_Common::subStr($description, 0, 100, "...");
        } catch (Typecho_Exception $e) {
            return "";
        }
    }

    /**
     * 统一解析笔记
     * @param array $content
     * @param array $struct
     * @return array
     * @throws Typecho_Exception
     */
    public function commonNoteStruct($content, $struct)
    {

        $markdown = $this->commonParseMarkdown($content['text']);
        $text = $content['type'] == "post_draft" || isset($struct['text']) ? $markdown : "";
        $description = isset($struct['description']) ? $this->commonParseDescription($markdown) : "";
        $filter = $this->singletonWidget('Widget_Abstract_Contents')->filter($content);

        return array(
            'cid' => $content["cid"],
            'title' => $content['title'],
            'slug' => $content['slug'],
            'created' => $content['created'],
            'modified' => $content['modified'],
            'text' => $text,
            'order' => $content['order'],
            'authorId' => $content['authorId'],
            'template' => $content['template'],
            'type' => $content['type'],
            'status' => $content['status'],
            'password' => $content['password'],
            'commentsNum' => $content['commentsNum'],
            'allowComment' => $content['allowComment'],
            'allowPing' => $content['allowPing'],
            'allowFeed' => $content['allowFeed'],
            'parent' => $content['parent'],

            'permalink' => $filter['permalink'],
            'description' => $description,
            'fields' => $this->commonFields($content["cid"]),
            'categories' => $this->commonMetasNames($content['cid'], true),
            'tags' => $this->commonMetasNames($content['cid'], false),
        );
    }

    /**
     * 获取分类和标签的字符串
     * @param int $cid
     * @param boolean $isCategory
     * @return string
     */
    public function commonMetasNames($cid, $isCategory)
    {
        $relationships = $this->db->fetchAll($this->db->select()->from('table.relationships')
            ->where('cid = ?', $cid));
        $meta = array();
        $type = $isCategory ? "category" : "tag";
        foreach ($relationships as $id) {
            $metas = $this->db->fetchAll($this->db->select()->from('table.metas')
                ->where('mid = ?', $id['mid']));
            foreach ($metas as $row) {
                if ($row['type'] == $type) {
                    $meta[] = $row['name'];
                }
            }
        }
        return implode(",", $meta);
    }

    /**
     * 统计文字字数
     * @param string $from
     * @param string $type
     * @return int
     */
    public function getCharacters($from, $type)
    {
        $chars = 0;
        $select = $this->db->select('text')
            ->from($from)
            ->where('type = ?', $type);
        $rows = $this->db->fetchAll($select);
        foreach ($rows as $row) {
            $chars += mb_strlen($row['text'], 'UTF-8');
        }
        return $chars;
    }

    /**
     * 获取附件
     * @param string $cid
     * @return string
     */
    public function commonFields($cid)
    {
        $fields = array();
        $rows = $this->db->fetchAll($this->db->select()->from('table.fields')
            ->where('cid = ?', $cid));
        foreach ($rows as $row) {
            $fields[] = array(
                "name" => $row['name'],
                "type" => $row['type'],
                "value" => $row[$row['type'] . '_value']
            );
        }
        return json_encode($fields);
    }


    /**
     * 统一评论
     * @param array $comments
     * @param array $struct
     * @return array
     */
    public function commonCommentsStruct($comments, $struct)
    {
        return array(
            'coid' => $comments['coid'],
            'cid' => $comments['cid'],
            'created' => $comments['created'],
            'author' => $comments['author'],
            'authorId' => $comments['authorId'],
            'ownerId' => $comments['ownerId'],
            'mail' => $comments['mail'],
            'url' => $comments['url'],
            'ip' => $comments['ip'],
            'agent' => $comments['agent'],
            'text' => $comments['text'],
            'type' => $comments['type'],
            'status' => $comments['status'],
            'parent' => $comments['parent'],

            'permalink' => "",
            'title' => $comments['title'],
        );
    }

    /**
     * 统一分类
     * @param $categories
     * @param $struct
     * @return array
     */
    public function commonCategoryTagStruct($categories, $struct)
    {
        return array(
            'mid' => $categories->mid,
            'name' => $categories->name,
            'slug' => $categories->slug,
            'type' => $categories->type,
            'description' => $categories->description,
            'count' => $categories->count,
            'order' => $categories->order,
            'parent' => $categories->parent,

            'permalink' => $categories->permalink,
        );
    }

    /**
     * 统一附件
     * @param $attachments
     * @param $struct
     * @return array
     */
    public function commonMediasStruct($attachments, $struct)
    {
        return array(
            'cid' => $attachments->cid,
            'title' => $attachments->title,
            'slug' => $attachments->slug,
            'created' => $attachments->created,
            'size' => $attachments->attachment->size,
            'url' => $attachments->attachment->url,
            'path' => $attachments->attachment->path,
            'mime' => $attachments->attachment->mime,
            'commentsNum' => $attachments->commentsNum,
            'description' => $attachments->attachment->description,

            'parent_title' => $attachments->parentPost->title,
            'parent_cid' => $attachments->parentPost->cid,
            'parent_type' => $attachments->parentPost->type,

        );
    }

    /**
     * 常用统计
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Widget_Exception
     * @throws Typecho_Exception
     * @access public
     */
    public function NbGetStat($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }
        $statArray = array(
            "post" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('authorId = ?', $this->uid))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('authorId = ?', $this->uid))->num,
                "waiting" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ? OR type = ?', 'post', 'post_draft')
                    ->where('status = ?', 'waiting')
                    ->where('authorId = ?', $this->uid))->num,
                "draft" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post_draft')
                    ->where('authorId = ?', $this->uid))->num,
                "hidden" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'hidden')
                    ->where('authorId = ?', $this->uid))->num,
                "private" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'private')
                    ->where('authorId = ?', $this->uid))->num,
                "textSize" => $this->getCharacters("table.contents", "post")
            ),
            "page" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('authorId = ?', $this->uid))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('status = ?', 'publish')
                    ->where('authorId = ?', $this->uid))->num,
                "hidden" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('status = ?', 'hidden')
                    ->where('authorId = ?', $this->uid))->num,
                "draft" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('type = ?', 'page_draft')
                    ->where('authorId = ?', $this->uid))->num,
                "textSize" => $this->getCharacters("table.contents", "page")
            ),
            "comment" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid))->num,
                "me" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('authorId = ?', $this->uid))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('status = ?', 'approved'))->num,
                "waiting" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('status = ?', 'waiting'))->num,
                "spam" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('ownerId = ?', $this->uid)
                    ->where('status = ?', 'spam'))->num,
                "textSize" => $this->getCharacters("table.comments", "comment")
            ),
            "categories" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'category'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'category')
                    ->where('count != ?', '0'))->num
            ),
            "tags" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'tag'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('type = ?', 'tag')
                    ->where('count != ?', '0'))->num
            ),
            "medias" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('authorId = ?', $this->uid)
                    ->where('type = ?', 'attachment'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('authorId = ?', $this->uid)
                    ->where('type = ?', 'attachment')
                    ->where('parent != ?', '0'))->num
            )
        );

        return array(true, $statArray);
    }

    /**
     * 获取指定id的post
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $postId
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPost($union, $userName, $password, $postId, $struct)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $select = $this->db->select()
            ->from('table.contents')
            ->where('authorId = ?', $this->uid)
            ->where('cid = ?', $postId);

        $fetchAll = $this->db->fetchAll($select);
        if (empty($fetchAll)) {
            return new IXR_Error(403, "不存在此文章");
        } else {
            return array(true, $this->commonNoteStruct($fetchAll[0], $struct));
        }

    }

    /**
     * 获取指定id的page
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $postId
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPage($union, $userName, $password, $postId, $struct)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        return $this->NbGetPost($union, $userName, $password, $postId, $struct);
    }

    /**
     * 获取文章
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPosts($union, $userName, $password, $struct)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $status = empty($struct['status']) ? "all" : $struct['status'];
        $type = isset($struct['type']) && 'page' == $struct['type'] ? 'page' : 'post';

        $select = $this->db->select()
            ->from('table.contents')
            ->where('authorId = ?', $this->uid);

        switch ($status) {
            case "draft":
                $select->where('type = ?', $type . '_draft');
                break;
            case "all":
                $select->where('type = ?', $type);
                break;
            default:
                $select->where('type = ?', $type);
                $select->where('status = ?', $status);
        }

        if (!empty($struct['keywords'])) {
            $searchQuery = '%' . str_replace(' ', '%', $struct['keywords']) . '%';
            $select->where('table.contents.title LIKE ? OR table.contents.text LIKE ?', $searchQuery, $searchQuery);
        }

        $pageSize = empty($struct['number']) ? 10 : abs(intval($struct['number']));
        $currentPage = empty($struct['offset']) ? 1 : ceil(abs(intval($struct['offset'])) / $pageSize);

        $select->order('table.contents.created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize);

        try {
            $fetchAll = $this->db->fetchAll($select);
            $postStruct = array();

            foreach ($fetchAll as $row) {
                $postStruct[] = $this->commonNoteStruct($row, $struct);
            }
            return array(true, $postStruct);

        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

    }

    /**
     * 获取独立页面
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetPages($union, $userName, $password, $struct)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        $struct['type'] = "page";
        return $this->NbGetPosts($union, $userName, $password, $struct);
    }

    /**
     * 撰写文章
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function NbNewPost($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $input = array();
        $type = isset($content['type']) && 'page' == $content['type'] ? 'page' : 'post';

        $input['title'] = trim($content['title']) == NULL ? _t('未命名文档') : $content['title'];

        if (isset($content['slug'])) {
            $input['slug'] = $content['slug'];
        }

        $input['text'] = !empty($content['text']) ? $content['text'] : NULL;
        $input['text'] = $this->pluginHandle()->textFilter($input['text'], $this);

        $input['password'] = isset($content["password"]) ? $content["password"] : NULL;
        $input['order'] = isset($content["order"]) ? $content["order"] : NULL;

        $input['tags'] = !empty($content['tags']) && is_array($content['tags']) ? implode(',', $content['tags']) : NULL;
        $input['category'] = array();

        if (isset($content['cid'])) {
            $input['cid'] = $content['cid'];
        }

        if ('page' == $type && isset($content['template'])) {
            $input['template'] = $content['template'];
        }

        if (isset($content['dateCreated'])) {
            /** 解决客户端与服务器端时间偏移 */
            $input['created'] = $content['dateCreated']->getTimestamp() - $this->options->timezone + $this->options->serverTimezone;
        }

        if (isset($content['fields'])) {
            $fields = json_decode($content['fields'], true);
            foreach ($fields as $field) {
                if (!is_array($field["value"])) {
                    $input['fields'][$field["name"]] = array(
                        $field["type"], $field["value"]
                    );
                }
            }
        }

        if (!empty($content['categories']) && is_array($content['categories'])) {
            foreach ($content['categories'] as $category) {
                if (!$this->db->fetchRow($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category))) {
                    $this->NewCategory($union, $userName, $password, array('name' => $category));
                }

                $input['category'][] = $this->db->fetchObject($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category)
                    ->limit(1))->mid;
            }
        }

        $input['allowComment'] = isset($content['allow_comments']) ? $content['allow_comments'] : $this->options->defaultAllowComment;
        $input['allowPing'] = isset($content['allow_pings']) ? $content['allow_pings'] : $this->options->defaultAllowPing;
        $input['allowFeed'] = isset($content['allow_feed']) ? $content['allow_feed'] : $this->options->defaultAllowFeed;

        /** 调整状态 */
        $status = isset($content["status"]) ? $content["status"] : "publish";
        $input['visibility'] = isset($content["visibility"]) ? $content["visibility"] : $status;
        if (in_array($status, array('publish', 'waiting', 'private', 'hidden'))) {
            $input['do'] = 'publish';
            if ('private' == $status) {
                $input['private'] = 1;
            }
        } else {
            $input['do'] = 'save';
        }

        /** 对未归档附件进行归档 */
        $unattached = $this->db->fetchAll($this->select()->where('table.contents.type = ? AND
        (table.contents.parent = 0 OR table.contents.parent IS NULL)', 'attachment'), array($this, 'filter'));

        if (!empty($unattached)) {
            foreach ($unattached as $attach) {
                if (false !== strpos($input['text'], $attach['attachment']->url)) {
                    if (!isset($input['attachment'])) {
                        $input['attachment'] = array();
                    }
                    $input['attachment'][] = $attach['cid'];
                }
            }
        }

        /** 调用已有组件 */
        try {

            $input['markdown'] = true; // 南博仅支持Markdown，所以必须开启xmlrpc md
            Helper::options()->markdown = true;
            Helper::options()->xmlrpcMarkdown = true;

            /** 插入 */
            $this->singletonWidget('page' == $type ? 'Widget_Contents_Page_Edit' : 'Widget_Contents_Post_Edit', NULL, $input, false)->action();
            return array(true, $this->singletonWidget('Widget_Notice')->getHighlightId());
        } catch (Typecho_Widget_Exception $e) {
            new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 自定义字段
     * @param $union
     * @param $userName
     * @param $password
     * @param $content
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection PhpIncludeInspection
     */
    public function NbFieldPost($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $type = isset($content['type']) && 'page' == $content['type'] ? 'page' : 'post';
        $widget = $this->singletonWidget(
            'page' == $type ? 'Widget_Contents_Page_Edit' : 'Widget_Contents_Post_Edit',
            NULL,
            NULL,
            false
        );

        ob_start();
        $configFile = $this->options->themeFile($this->options->theme, 'functions.php');
        $layout = new Typecho_Widget_Helper_Layout();

        $widget->pluginHandle()->getDefaultFieldItems($layout);

        if (file_exists($configFile)) {
            require_once $configFile;

            if (function_exists('themeFields')) {
                themeFields($layout);
            }

            if (function_exists($widget->themeCustomFieldsHook)) {
                call_user_func($widget->themeCustomFieldsHook, $layout);
            }
        }

        $layout->render();
        $div = ob_get_contents();
        ob_end_clean();

        return array(
            true,
            $div
        );
    }

    /**
     * 编辑文章
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbEditPost($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }
        return $this->NbNewPost($union, $userName, $password, $content);
    }

    /**
     * 删除文章
     * @param string $union
     * @param mixed $userName
     * @param mixed $password
     * @param int $postId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbDeletePost($union, $userName, $password, $postId)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        try {
            $this->singletonWidget('Widget_Contents_Post_Edit', NULL, "cid={$postId}", false)->deletePost();
            return array(true, null);
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 获取评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetComments($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $select = $this->db->select('table.comments.coid',
            'table.comments.*',
            'table.contents.title'
        )->from('table.comments')->join('table.contents', 'table.comments.cid = table.contents.cid', Typecho_Db::LEFT_JOIN);
        $select->where('table.comments.ownerId = ?', $this->uid);

        if (!empty($struct['cid'])) {
            $select->where('table.comments.cid = ?', $struct['cid']);
        }

        if (!empty($struct['mail'])) {
            $select->where('table.comments.mail = ?', $struct['mail']);
        }

        if (!empty($struct['status'])) {
            $select->where('table.comments.status = ?', $struct['status']);
        }

        $pageSize = empty($struct['number']) ? 10 : abs(intval($struct['number']));
        $currentPage = empty($struct['offset']) ? 1 : ceil(abs(intval($struct['offset'])) / $pageSize);

        $select->order('created', Typecho_Db::SORT_DESC)
            ->page($currentPage, $pageSize);

        try {
            $fetchAll = $this->db->fetchAll($select);
            $postStruct = array();

            foreach ($fetchAll as $row) {
                $postStruct[] = $this->commonCommentsStruct($row, $struct);
            }
            return array(true, $postStruct);

        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 删除评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $commentId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbDeleteComment($union, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $commentId = abs(intval($commentId));
        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', $commentId);

        if (!$commentWidget->commentIsWriteable($where)) {
            return new IXR_Error(403, _t('无法编辑此评论'));
        }

        return array(intval($this->singletonWidget('Widget_Abstract_Comments')->delete($where)) > 0, null);
    }

    /**
     * 创建评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param mixed $path
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function NbNewComment($union, $userName, $password, $path, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        if (is_numeric($path)) {
            $post = $this->singletonWidget('Widget_Archive', 'type=single', 'cid=' . $path, false);
        } else {
            /** 检查目标地址是否正确*/
            $pathInfo = Typecho_Common::url(substr($path, strlen($this->options->index)), '/');
            $post = Typecho_Router::match($pathInfo);
        }

        /** 这样可以得到cid或者slug*/
        if (!isset($post) || !($post instanceof Widget_Archive) || !$post->have() || !$post->is('single')) {
            return new IXR_Error(404, _t('这个目标地址不存在'));
        }

        $input = array();
        $input['permalink'] = $post->pathinfo;
        $input['type'] = 'comment';

        if (isset($struct['author'])) {
            $input['author'] = $struct['author'];
        }

        if (isset($struct['mail'])) {
            $input['mail'] = $struct['mail'];
        }

        if (isset($struct['url'])) {
            $input['url'] = $struct['url'];
        }

        if (isset($struct['parent'])) {
            $input['parent'] = $struct['parent'];
        }

        if (isset($struct['text'])) {
            $input['text'] = $struct['text'];
        }

        try {

            Helper::options()->commentsAntiSpam = false; //临时评论关闭反垃圾保护
            $commentWidget = $this->singletonWidget('Widget_Feedback', 'checkReferer=false', $input, false);
            $commentWidget->action();

            return array(true, $commentWidget->coid);
        } catch (Typecho_Exception $e) {
            return new IXR_Error(500, $e->getMessage());
        }
    }

    /**
     * 获取评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $commentId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetComment($union, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $comments = $this->singletonWidget('Widget_Comments_Edit', NULL, 'do=get&coid=' . intval($commentId), false);

        if (!$comments->have()) {
            return new IXR_Error(404, _t('评论不存在'));
        }

        if (!$comments->commentIsWriteable()) {
            return new IXR_Error(403, _t('没有获取评论的权限'));
        }

        $commentsStruct = $this->commonCommentsStruct((array)$comments, null);

        return array(true, $commentsStruct);
    }

    /**
     * 编辑评论
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $commentId
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbEditComment($union, $userName, $password, $commentId, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $commentId = abs(intval($commentId));
        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', $commentId);

        if (!$commentWidget->commentIsWriteable($where)) {
            return new IXR_Error(403, _t('无法编辑此评论'));
        }

        $input = array();

        if (isset($struct['created'])) {
            $input['created'] = $struct['created'];
        }

        if (isset($struct['status'])) {
            $input['status'] = $struct['status'];
        } else {
            $input['status'] = "approved";
        }

        if (isset($struct['text'])) {
            $input['text'] = $struct['text'];
        }

        if (isset($struct['author'])) {
            $input['author'] = $struct['author'];
        }

        if (isset($struct['url'])) {
            $input['url'] = $struct['url'];
        }

        if (isset($struct['mail'])) {
            $input['mail'] = $struct['mail'];
        }

        $result = $commentWidget->update((array)$input, $where);

        if (!$result) {
            return new IXR_Error(404, _t('评论不存在'));
        }

        return array(true, null);
    }

    /**
     * NewMedia
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $data
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     * @noinspection PhpUndefinedMethodInspection
     */
    public function NbNewMedia($union, $userName, $password, $data)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        // 南博中上传附件是经过base64编码后传输的，这里需要解码
        if (isset($data['bytes'])) {
            $data['bytes'] = base64_decode($data['bytes']);
        }

        $result = Widget_Upload::uploadHandle($data);

        if (false === $result) {
            return new IXR_Error(500, _t('上传失败'));
        } else {

            $insertId = $this->insert(array(
                'title' => $result['name'],
                'slug' => $result['name'],
                'type' => 'attachment',
                'status' => 'publish',
                'text' => serialize($result),
                'allowComment' => 1,
                'allowPing' => 0,
                'allowFeed' => 1
            ));

            $this->db->fetchRow($this->select()->where('table.contents.cid = ?', $insertId)
                ->where('table.contents.type = ?', 'attachment'), array($this, 'push'));

            /** 增加插件接口 */
            $this->pluginHandle()->upload($this);

            $object = array(
                'name' => $this->attachment->name,
                'url' => $this->attachment->url
            );

            return array(true, $object);
        }
    }

    /**
     * 获取所有的分类
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetCategories($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $categories = $this->singletonWidget('Widget_Metas_Category_List');

        /** 初始化category数组*/
        $categoryStructs = array();
        while ($categories->next()) {
            $categoryStructs[] = $this->commonCategoryTagStruct($categories, null);
        }

        return array(true, $categoryStructs);
    }

    /**
     * 添加一个新的分类
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $category
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbNewCategory($union, $userName, $password, $category)
    {
        if (!$this->access($union, $userName, $password, "editor")) {
            return $this->error;
        }

        /** 开始接受数据 */
        $input['name'] = $category['name'];
        $input['slug'] = Typecho_Common::slugName(empty($category['slug']) ? $category['name'] : $category['slug']);
        $input['parent'] = isset($category['parent_id']) ? $category['parent_id'] :
            (isset($category['parent']) ? $category['parent'] : 0);
        $input['description'] = isset($category['description']) ? $category['description'] : $category['name'];
        $input['do'] = 'insert';

        /** 调用已有组件 */
        try {
            /** 插入 */
            $categoryWidget = $this->singletonWidget('Widget_Metas_Category_Edit', NULL, $input, false);
            $categoryWidget->action();
            return array(true, $categoryWidget->mid);
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), "无法添加分类");
        }
    }

    /**
     * 编辑分类
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $category
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbEditCategory($union, $userName, $password, $category)
    {
        if (!$this->access($union, $userName, $password, "editor")) {
            return $this->error;
        }

        if (empty($category['mid'])) {
            return new IXR_Error(403, "没有设置分类mid");
        }
        $input['mid'] = $category['mid'];
        if (!$this->db->fetchRow($this->db->select('mid')
            ->from('table.metas')->where('type = ? AND mid = ?', 'category', $input['mid']))) {
            return new IXR_Error(403, "没有查找到分类");
        }

        /** 开始接受数据 */
        $input['name'] = $category['name'];
        $input['slug'] = Typecho_Common::slugName(empty($category['slug']) ? $category['name'] : $category['slug']);
        $input['parent'] = isset($category['parent_id']) ? $category['parent_id'] :
            (isset($category['parent']) ? $category['parent'] : 0);
        $input['description'] = isset($category['description']) ? $category['description'] : $category['name'];
        $input['do'] = 'update';

        /** 调用已有组件 */
        try {
            /**更新 */
            $categoryWidget = $this->singletonWidget('Widget_Metas_Category_Edit', NULL, $input, false);
            $categoryWidget->action();
            return array(true, null);
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), "无法编辑分类");
        }
    }

    /**
     * 删除分类
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param int $categoryId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbDeleteCategory($union, $userName, $password, $categoryId)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'editor')) {
            return $this->error;
        }

        try {
            $this->singletonWidget('Widget_Metas_Category_Edit', NULL, 'do=delete&mid=' . intval($categoryId), false);
            return array(true, null);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), "删除分类失败");
        }
    }

    /**
     * 获取所有的标签
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbGetTags($union, $userName, $password)
    {
        if (!$this->access($union, $userName, $password, "contributor")) {
            return ($this->error);
        }

        try {
            $tags = $this->singletonWidget('Widget_Metas_Tag_Cloud');

            /** 初始化category数组*/
            $categoryStructs = array();
            while ($tags->next()) {
                $categoryStructs[] = $this->commonCategoryTagStruct($tags, null);
            }

            return array(true, $categoryStructs);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), "获取标签失败");
        }

    }

    /**
     * new 独立页面
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbNewPage($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        $content['type'] = 'page';
        return $this->NbNewPost($union, $userName, $password, $content);
    }

    /**
     * 编辑独立页面
     *
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NbEditPage($union, $userName, $password, $content)
    {
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        $content['type'] = 'page';
        return $this->NbNewPost($union, $userName, $password, $content);
    }


    /**
     * 获取系统选项
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetOptions($union, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = array();
        $this->options->siteUrl = rtrim($this->options->siteUrl, '/');
        foreach ($options as $option) {
            if (isset($this->options->{$option})) {
                $o = array(
                    'name' => $option,
                    'user' => $this->uid,
                    'value' => $this->options->{$option}
                );
            } else {
                $select = $this->db->select()->from('table.options')
                    ->where('name = ?', $option)
                    ->where('user = ? ', 0)
                    ->order('user', Typecho_Db::SORT_DESC);
                $o = $this->db->fetchRow($select);
            }
            $struct[] = array(
                $o['name'],
                $o['user'],
                $o['value']
            );
        }

        return array(true, $struct);
    }

    /**
     * 设置系统选项
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbSetOptions($union, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = array();
        foreach ($options as $object) {
            if ($this->db->query($this->db->update('table.options')
                    ->rows(array('value' => $object[2]))
                    ->where('name = ?', $object[0])) > 0) {
                $struct[] = array(
                    $object[0],
                    $object[2]
                );
            }
        }

        return array(true, $struct);
    }

    /**
     * 获取媒体文件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetMedias($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "contributor")) {
            return $this->error;
        }

        $input = array();

        if (!empty($struct['parent'])) {
            $input['parent'] = $struct['parent'];
        }

        if (!empty($struct['mime'])) {
            $input['mime'] = $struct['mime'];
        }

        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = abs(intval($struct['number']));
        }

        if (!empty($struct['offset'])) {
            $offset = abs(intval($struct['offset']));
            $input['page'] = ceil($offset / $pageSize);
        }

        try {
            $attachments = $this->singletonWidget('Widget_Contents_Attachment_Admin', 'pageSize=' . $pageSize, $input, false);
            $attachmentsStruct = array();

            while ($attachments->next()) {
                $attachmentsStruct[] = $this->commonMediasStruct($attachments, null);
            }
            return array(true, $attachmentsStruct);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), "获取标签失败");
        }

    }

    /**
     * 清理未归档的附件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbClearMedias($union, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'administrator')) {
            return $this->error;
        }
        $input = array();
        $input["do"] = "clear";

        try {
            $mediaWidget = $this->singletonWidget('Widget_Contents_Attachment_Edit', null, $input, false);
            $mediaWidget->action();
            return array(true, null);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), "清理未归档的文件失败");
        }

    }

    /**
     * 删除附件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbDeleteMedia($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'contributor')) {
            return $this->error;
        }
        if (empty($struct["cids"]) || !is_array($struct["cids"])) {
            return new IXR_Error(403, "没有设置cids");
        }

        $input = array();
        $input["do"] = "delete";
        $input["cid"] = $struct["cids"];

        try {
            $mediaWidget = $this->singletonWidget('Widget_Contents_Attachment_Edit', null, $input, false);
            $mediaWidget->action();
            return array(true, null);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), "删除文件失败");
        }

    }

    /**
     * 编辑附件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbEditMedia($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, 'contributor')) {
            return $this->error;
        }

        $input = array();
        if (empty($struct["cid"])) {
            return new IXR_Error(403, "没有设置附件cid");
        }
        if (empty($struct["name"])) {
            return new IXR_Error(403, "没有设置标题");
        }
        if (!empty($struct["slug"])) {
            $input["slug"] = $struct["slug"];
        }
        $input["cid"] = $struct["cid"];
        $input["name"] = $struct["name"];
        $input["description"] = empty($struct["description"]) ? "" : $struct["description"];

        $input["do"] = "update";
        try {
            $mediaWidget = $this->singletonWidget('Widget_Contents_Attachment_Edit', null, $input, false);
            $mediaWidget->action();
            return array(true, null);
        } catch (Typecho_Exception $e) {
            return new IXR_Error($e->getCode(), "删除文件失败");
        }

    }

    /**
     * 内容替换 - 插件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlDialectInspection
     * @noinspection PhpSingleStatementWithBracesInspection
     */
    public function NbPluginReplace($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }
        if (empty($struct['former']) || empty($struct['last']) || empty($struct['object'])) {
            return new IXR_Error(403, _t('参数不齐,无法替换'));
        } else {
            $former = $struct['former'];
            $last = $struct['last'];
            $object = $struct['object'];
            $array = array(
                'post|text',
                'post|title',
                'page|text',
                'page|title',
                'field|thumb',
                'field|mp4',
                'field|fm',
                'comment|text',
                'comment|url'
            );
            if (in_array($object, $array)) {
                $prefix = $this->db->getPrefix();
                $obj = explode("|", $object);
                $type = $obj[0];
                $aim = $obj[1];

                try {
                    switch ($type) {
                        case "post":
                        case "page":
                            $data_name = $prefix . 'contents';
                            $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}') WHERE type='{$type}'");
                            break;
                        case "field":
                            $data_name = $prefix . 'fields';
                            $this->db->query("UPDATE `{$data_name}` SET `str_value`=REPLACE(`str_value`,'{$former}','{$last}')  WHERE name='{$aim}'");
                            break;
                        case "comment":
                            $data_name = $prefix . 'comments';
                            $this->db->query("UPDATE `{$data_name}` SET `{$aim}`=REPLACE(`{$aim}`,'{$former}','{$last}')");
                    }
                    return array(true, null);
                } catch (Typecho_Widget_Exception $e) {
                    return new IXR_Error($e->getCode(), $e->getMessage());
                }

            } else {
                return new IXR_Error(403, _t('不含此参数,无法替换'));
            }
        }
    }

    /**
     * 友情链接管理 - 插件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbPluginDynamics($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        if (!isset($this->options->plugins['activated']['Dynamics'])) {
            return new IXR_Error(403, "没有启用我的动态插件");
        }

        if (!is_array($struct)) {
            return new IXR_Error(403, "struct不是一个数组对象");
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if ($struct['method'] == "insert") {
            try {
                if (empty($struct['dynamic'])) {
                    return new IXR_Error(403, "没有设定dynamic对象");
                }
                $dynamicMap = $struct['dynamic'];
                $dynamic = array();
                if (empty($dynamicMap['text'])) {
                    return new IXR_Error(403, "没有写动态内容");
                }
                if (!empty($dynamicMap['status'])) {
                    $dynamic['status'] = $dynamicMap['status'];
                }
                $date = (new Typecho_Date($this->options->gmtTime))->time();
                $dynamic['text'] = $dynamicMap['text'];
                $dynamic['authorId'] = $this->uid;
                $dynamic['modified'] = $date;
                if (isset($dynamicMap['did'])) $dynamic['did'] = intval($dynamicMap['did']);

                if (isset($dynamic['did'])) {
                    /** 更新数据 */
                    $this->db->query($this->db->update('table.dynamics')->rows($dynamic)->where('did = ?', $dynamic['did']));
                } else {
                    $dynamic['created'] = $date;
                    /** 插入数据 */
                    $dynamic['did'] = $this->db->query($this->db->insert('table.dynamics')->rows($dynamic));
                }
                return array(true, $dynamic);
            } catch (Typecho_Widget_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        } else if ($struct['method'] == "delete") {
            $dids = $struct['dids'];
            if (!is_array($dids)) {
                return new IXR_Error(403, "dids 不是一个数组对象");
            }
            $deleteCount = 0;
            foreach ($dids as $did) {
                if ($this->db->query($this->db->delete('table.dynamics')->where('did = ?', $did))) {
                    $deleteCount++;
                }
            }
            return array(true, $deleteCount);
        } else {
            try {
                $select = $this->db->select()->from('table.dynamics')
                    ->where('authorId = ?', $this->uid);

                if (!empty($struct['status'])) {
                    if ($struct['status'] != "all") {
                        $select->where('status = ?', $struct['status']);
                    }
                }

                $pageSize = empty($struct['number']) ? 10 : abs(intval($struct['number']));
                $currentPage = empty($struct['offset']) ? 1 : ceil(abs(intval($struct['offset'])) / $pageSize);
                $select->order('created', Typecho_Db::SORT_DESC)
                    ->page($currentPage, $pageSize);

                $all = $this->db->fetchAll($select);
                $dynamics = array();
                foreach ($all as $dic) {
                    $dic["title"] = date("m月d日, Y年", $dic["created"]);
                    $dic["permalink"] = Dynamics_Plugin::applyUrl($dic["did"], true);
                    $dynamics[] = $dic;
                }
                return array(true, $dynamics);
            } catch (Typecho_Widget_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        }
    }

    /**
     * 友情链接管理 - 插件
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbPluginLinks($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        if (!isset($this->options->plugins['activated']['Links'])) {
            return new IXR_Error(403, "没有启用插件");
        }

        if (!is_array($struct)) {
            return new IXR_Error(403, "struct不是一个数组对象");
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if ($struct['method'] == "insert") {
            try {
                if (!isset($struct['link'])) {
                    return new IXR_Error(403, "没有link对象");
                }
                $linkMap = $struct['link'];

                if (!isset($linkMap['name'])) {
                    return new IXR_Error(403, "没有设定名字");
                }
                if (!isset($linkMap['url'])) {
                    return new IXR_Error(403, "没有设定链接地址");
                }

                $link = array();
                $link['name'] = $linkMap['name'];
                $link['url'] = $linkMap['url'];
                if (isset($linkMap['lid'])) $link['lid'] = intval($linkMap['lid']);
                if (isset($linkMap['image'])) $link['image'] = $linkMap['image'];
                if (isset($linkMap['description'])) $link['description'] = $linkMap['description'];
                if (isset($linkMap['user'])) $link['user'] = $linkMap['user'];
                if (isset($linkMap['order'])) $link['order'] = $linkMap['order'];
                if (isset($linkMap['sort'])) $link['sort'] = $linkMap['sort'];

                if (isset($link['lid'])) {
                    /** 更新数据 */
                    $this->db->query($this->db->update('table.links')->rows($link)->where('lid = ?', $link['lid']));
                } else {
                    $link['order'] = $this->db->fetchObject($this->db->select(array('MAX(order)' => 'maxOrder'))->from('table.links'))->maxOrder + 1;
                    /** 插入数据 */
                    $link['lid'] = $this->db->query($this->db->insert('table.links')->rows($link));
                }
                return array(true, $link);
            } catch (Typecho_Widget_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        } else if ($struct['method'] == "delete") {
            $lids = $struct['lids'];
            if (!is_array($lids)) {
                return new IXR_Error(403, "lids 不是一个数组对象");
            }
            $deleteCount = 0;
            foreach ($lids as $lid) {
                if ($this->db->query($this->db->delete('table.links')->where('lid = ?', $lid))) {
                    $deleteCount++;
                }
            }
            return array(true, $deleteCount);
        } else {
            try {
                $select = $this->db->select()->from('table.links');

                $pageSize = empty($struct['number']) ? 10 : abs(intval($struct['number']));
                $currentPage = empty($struct['offset']) ? 1 : ceil(abs(intval($struct['offset'])) / $pageSize);
                $select->order('order', Typecho_Db::SORT_ASC)
                    ->page($currentPage, $pageSize);

                $links = $this->db->fetchAll($select);
                return array(true, $links);
            } catch (Typecho_Widget_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        }
    }

    /**
     * 插件配置管理
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    public function NbConfigPlugin($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setPluginAble == 0) {
                return array(false, "你已关闭插件设置能力\n可以在 Aidnabo 插件里开启设置能力");
            }
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if (!isset($struct['pluginName'])) {
            return new IXR_Error(403, "没有设定插件名字");
        }

        if (!isset($this->options->plugins['activated']{$struct['pluginName']})) {
            return new IXR_Error(403, "没有启用插件");
        }

        $className = $struct['pluginName'] . "_Plugin";

        if ($struct['method'] == "set") {

            if (empty($struct['settings'])) {
                return new IXR_Error(403, "settings 不规范");
            }

            $settings = json_decode($struct['settings'], true);

            ob_start();
            $form = new Typecho_Widget_Helper_Form();
            call_user_func(array($className, 'config'), $form);

            foreach ($settings as $key => $val) {
                if (!empty($form->getInput($key))) {
                    $_GET{$key} = $settings{$key};
                    $form->getInput($key)->value($val);
                }
            }

            /** 验证表单 */
            if ($form->validate()) {
                return new IXR_Error(403, "表中有数据不符合配置要求");
            }

            $settings = $form->getAllRequest();
            ob_end_clean();

            try {
                $edit = $this->singletonWidget(
                    'Widget_Plugins_Edit',
                    NULL,
                    NULL,
                    false
                );

                if (!$edit->configHandle($struct['pluginName'], $settings, false)) {
                    Widget_Plugins_Edit::configPlugin($struct['pluginName'], $settings);
                }

                return array(true, "设置成功");
            } catch (Typecho_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        } else {

            ob_start();
            $config = $this->singletonWidget(
                'Widget_Plugins_Config',
                null,
                array(
                    "config" => $struct['pluginName']
                ),
                false
            );
            $form = $config->config();
            $form->setAction(NULL);
            $form->setAttribute("id", "form");
            $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
            $form->render();
            $string = ob_get_contents();
            $formLayout = $string;
            ob_end_clean();

            return array(true, $formLayout);
        }

    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    public function NbConfigProfile($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if (!isset($struct['option'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if ($struct['method'] == "set") {
            if (isset($this->options->plugins['activated']['Aidnabo'])) {
                if ($this->options->plugin("Aidnabo")->setOptionAble == 0) {
                    return array(false, "你已关闭基本设置能力\n可以在 Aidnabo 插件里开启设置能力");
                }
            }

            if (empty($struct['settings'])) {
                return new IXR_Error(403, "settings 不规范");
            }
            $settings = json_decode($struct['settings'], true);

            ob_start();
            $config = $this->singletonWidget(
                'Widget_Users_Profile',
                null,
                $settings,
                false
            );
            if ($struct['option'] == "profile") {
                $config->updateProfile();
            } else if ($struct['option'] == "options") {
                $config->updateOptions();
            } else if ($struct['option'] == "password") {
                $config->updatePassword();
//            } else if ($struct['option'] == "personal") {
//                $config->updatePersonal();
            }
            ob_end_clean();
            return array(true, "设置已经保存");
        } else {
            ob_start();
            $config = $this->singletonWidget(
                'Widget_Users_Profile',
                null,
                null,
                false
            );

            if ($struct['option'] == "profile") {
                $form = $config->profileForm();
            } else if ($struct['option'] == "options") {
                $form = $config->optionsForm();
            } else if ($struct['option'] == "password") {
                $form = $config->passwordForm();
//            } else if ($struct['option'] == "personal") {
//                $form = $config->personalFormList();
            } else {
                return new IXR_Error(403, "option 不规范");
            }

            $form->setAction(NULL);
            $form->setAttribute("id", "form");
            $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
            $form->render();
            $string = ob_get_contents();
            $formLayout = $string;
            ob_end_clean();

            return array(true, $formLayout);
        }
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    public function NbConfigOption($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if (!isset($struct['option'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        $alias = NULL;
        if ($struct['option'] == "general") {
            $alias = "Widget_Options_General";
        } else if ($struct['option'] == "discussion") {
            $alias = "Widget_Options_Discussion";
        } else if ($struct['option'] == "reading") {
            $alias = "Widget_Options_Reading";
        } else if ($struct['option'] == "permalink") {
            $alias = "Widget_Options_Permalink";
        } else {
            return new IXR_Error(403, "option 不规范");
        }

        if ($struct['method'] == "set") {

            if (isset($this->options->plugins['activated']['Aidnabo'])) {
                if ($this->options->plugin("Aidnabo")->setOptionAble == 0) {
                    return array(false, "你已关闭基本设置能力\n可以在 Aidnabo 插件里开启设置能力");
                }
            }

            if (empty($struct['settings'])) {
                return new IXR_Error(403, "settings 不规范");
            }
            $settings = json_decode($struct['settings'], true);

            ob_start();
            $config = $this->singletonWidget(
                $alias,
                null,
                $settings,
                false
            );
            if ($struct['option'] == "general") {
                $config->updateGeneralSettings();
            } else if ($struct['option'] == "discussion") {
                $config->updateDiscussionSettings();
            } else if ($struct['option'] == "reading") {
                $config->updateReadingSettings();
            } else if ($struct['option'] == "permalink") {
                $config->updatePermalinkSettings();
            }
            ob_end_clean();
            return array(true, "设置已经保存");
        } else {

            ob_start();
            $config = $this->singletonWidget(
                $alias,
                null,
                null,
                false
            );
            $form = $config->form();
            $form->setAction(NULL);
            $form->setAttribute("id", "form");
            $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
            $form->render();
            $string = ob_get_contents();
            $formLayout = $string;
            ob_end_clean();

            return array(true, $formLayout);
        }

    }

    /**
     * 主题配置管理
     *
     * @access public
     * @param string $union
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection PhpUndefinedMethodInspection
     */
    public function NbConfigTheme($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setThemeAble == 0) {
                return array(false, "你已关闭主题设置能力\n可以在 Aidnabo 插件里开启设置能力");
            }
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if (Widget_Themes_Config::isExists()) {

            if ($struct['method'] == "set") {

                if (empty($struct['settings'])) {
                    return new IXR_Error(403, "settings 不规范");
                }

                try {

                    $settings = json_decode($struct['settings'], true);
                    $theme = $this->options->theme;

                    ob_start();
                    $form = new Typecho_Widget_Helper_Form();
                    themeConfig($form);
                    $inputs = $form->getInputs();

                    if (!empty($inputs)) {
                        foreach ($inputs as $key => $val) {
                            $_GET{$key} = $settings{$key};
                            $form->getInput($key)->value($settings{$key});
                        }
                    }

                    /** 验证表单 */
                    if ($form->validate()) {
                        return new IXR_Error(403, "表中有数据不符合配置要求");
                    }

                    $settings = $form->getAllRequest();
                    ob_end_clean();

                    $db = Typecho_Db::get();
                    $themeEdit = $this->singletonWidget(
                        'Widget_Themes_Edit',
                        NULL,
                        NULL,
                        false
                    );

                    if (!$themeEdit->configHandle($settings, false)) {
                        if ($this->options->__get('theme:' . $theme)) {
                            $update = $db->update('table.options')
                                ->rows(array('value' => serialize($settings)))
                                ->where('name = ?', 'theme:' . $theme);
                            $db->query($update);
                        } else {
                            $insert = $db->insert('table.options')
                                ->rows(array(
                                    'name' => 'theme:' . $theme,
                                    'value' => serialize($settings),
                                    'user' => 0
                                ));
                            $db->query($insert);
                        }
                    }

                    return array(true, "外观设置已经保存");
                } catch (Typecho_Exception $e) {
                    return new IXR_Error($e->getCode(), $e->getMessage());
                }

            } else {

                ob_start();
                $config = $this->singletonWidget(
                    'Widget_Themes_Config',
                    null,
                    null,
                    false
                );
                $form = $config->config();
                $form->setAction(NULL);
                $form->setAttribute("id", "form");
                $form->setMethod(Typecho_Widget_Helper_Form::GET_METHOD);
                $form->render();
                $string = ob_get_contents();
                $formLayout = $string;
                ob_end_clean();

                return array(true, $formLayout);
            }
        } else {
            return new IXR_Error(403, "没有主题可配置");
        }

    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetPlugins($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        $target = isset($struct['option']) ? $struct['option'] : "typecho";
        $list = array();
        $activatedPlugins = $this->singletonWidget('Widget_Plugins_List@activated', 'activated=1');

        if ($activatedPlugins->have() || !empty($activatedPlugins->activatedPlugins)) {
            while ($activatedPlugins->next()) {
                $list[$activatedPlugins->name] = array(
                    "activated" => true,
                    "name" => $activatedPlugins->name,
                    "title" => $activatedPlugins->title,
                    "dependence" => $activatedPlugins->dependence,
                    "description" => strip_tags($activatedPlugins->description),
                    "version" => $activatedPlugins->version,
                    "homepage" => $activatedPlugins->homepage,
                    "author" => $activatedPlugins->author,
                    "config" => $activatedPlugins->config
                );
            }
        }

        $deactivatedPlugins = $this->singletonWidget('Widget_Plugins_List@unactivated', 'activated=0');

        if ($deactivatedPlugins->have() || !$activatedPlugins->have()) {
            while ($deactivatedPlugins->next()) {
                $list[$deactivatedPlugins->name] = array(
                    "activated" => false,
                    "name" => $deactivatedPlugins->name,
                    "title" => $deactivatedPlugins->title,
                    "dependence" => true,
                    "description" => strip_tags($deactivatedPlugins->description),
                    "version" => $deactivatedPlugins->version,
                    "homepage" => $deactivatedPlugins->homepage,
                    "author" => $deactivatedPlugins->author,
                    "config" => false
                );
            }
        }

        if ($target == "testore") {
            $activatedList = $this->options->plugins['activated'];
            if (isset($activatedList['TeStore'])) {
                $testore = $this->singletonWidget(
                    "TeStore_Action",
                    null,
                    null,
                    false
                );
                $storeList = array();
                $plugins = $testore->getPluginData();

                foreach ($plugins as $plugin) {
                    $thisPlugin = $list[$plugin['pluginName']];
                    $installed = array_key_exists($plugin['pluginName'], $list);
                    $activated = $installed ? $thisPlugin["activated"] : false;
                    $storeList[] = array(
                        "activated" => $activated,
                        "name" => $plugin['pluginName'],
                        "title" => $plugin['pluginName'],
                        "dependence" => $activated ? $thisPlugin["dependence"] : null,
                        "description" => strip_tags($plugin['desc']),
                        "version" => $plugin['version'],
                        "homepage" => $plugin['pluginUrl'],
                        "author" => strip_tags($plugin['authorHtml']),
                        "config" => $activated ? $thisPlugin["config"] : false,

                        "installed" => $installed,
                        "mark" => $plugin['mark'],
                        "zipFile" => $plugin['zipFile'],
                    );
                }

                return array(true, $storeList);
            } else {
                return array(false, "你没有安装 TeStore 插件");
            }
        } else {
            $callList = array();
            foreach ($list as $key => $info) {
                $callList[] = $info;
            }
            return array(true, $callList);
        }
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbSetPlugins($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setPluginAble == 0) {
                return array(false, "你已关闭插件设置能力\n可以在 Aidnabo 插件里开启设置能力");
            }
        }

        $target = isset($struct['option']) ? $struct['option'] : "typecho";
        if ($target == "testore") {
            $activatedList = $this->options->plugins['activated'];
            if (isset($activatedList['TeStore'])) {
                $authors = preg_split('/([,&])/', $struct['authorName']);
                foreach ($authors as $key => $val) {
                    $authors[$key] = trim($val);
                }
                $testore = $this->singletonWidget(
                    "TeStore_Action",
                    null,
                    array(
                        "plugin" => $struct['pluginName'],
                        "author" => implode('_', $authors),
                        "zip" => $struct['zipFile'],
                    ),
                    false
                );

                $isActivated = $activatedList[$struct['pluginName']];

                if ($struct['method'] == "activate") {
                    if ($isActivated) {
                        return array(false, "该插件已被安装过");
                    } else {
                        $testore->install();
                    }
                } else if ($struct['method'] == "deactivate") {
                    $testore->uninstall();
                }

                $notice = Json::decode(
                    Typecho_Cookie::get("__typecho_notice"), true
                )[0];

                return array(true, $notice);
            } else {
                return array(false, "你没有安装 TeStore 插件");
            }
        } else {
            try {
                $plugins = $this->singletonWidget(
                    'Widget_Plugins_Edit',
                    NULL,
                    NULL,
                    false
                );

                if ($struct['method'] == "activate") {
                    $plugins->activate($struct['pluginName']);

                } else if ($struct['method'] == "deactivate") {
                    $plugins->deactivate($struct['pluginName']);

                }

                $notice = Json::decode(
                    Typecho_Cookie::get("__typecho_notice"), true
                )[0];

                return array(true, $notice);
            } catch (Typecho_Widget_Exception $e) {
                return new IXR_Error(403, $e);
            }
        }

    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbGetThemes($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        $list = array();
        $themes = $this->singletonWidget('Widget_Themes_List');

        while ($themes->next()) {
            $list[] = array(
                "activated" => $themes->activated,
                "name" => $themes->name,
                "title" => $themes->title,
                "description" => strip_tags($themes->description),
                "version" => $themes->version,
                "homepage" => $themes->homepage,
                "author" => $themes->author,
                "config" => false
            );
        }
        return array(true, $list);
    }

    /**
     * @param $union
     * @param $userName
     * @param $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function NbSetThemes($union, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->access($union, $userName, $password, "administrator")) {
            return $this->error;
        }

        if (isset($this->options->plugins['activated']['Aidnabo'])) {
            if ($this->options->plugin("Aidnabo")->setThemeAble == 0) {
                return array(false, "你已关闭主题设置能力\n可以在 Aidnabo 插件里开启设置能力");
            }
        }

        try {
            $themes = $this->singletonWidget(
                'Widget_Themes_Edit',
                NULL,
                NULL,
                false
            );

            if ($struct['method'] == "changeTheme") {
                $themes->changeTheme($struct['themeName']);
                return array(true, "外观已经改变");
            } else {
                return new IXR_Error(403, "method 未知");
            }

        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error(403, $e);
        }
    }

    /**
     * pingbackPing
     *
     * @param string $source
     * @param string $target
     * @return IXR_Error|void
     * @throws Typecho_Exception
     * @access public
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection RegExpRedundantEscape
     * @noinspection PhpUndefinedMethodInspection
     */
    public function pingbackPing($source, $target)
    {
        /** 检查目标地址是否正确*/
        $pathInfo = Typecho_Common::url(substr($target, strlen($this->options->index)), '/');
        $post = Typecho_Router::match($pathInfo);

        /** 检查源地址是否合法 */
        $params = parse_url($source);
        if (false === $params || !in_array($params['scheme'], array('http', 'https'))) {
            return new IXR_Error(16, _t('源地址服务器错误'));
        }

        if (!Typecho_Common::checkSafeHost($params['host'])) {
            return new IXR_Error(16, _t('源地址服务器错误'));
        }

        /** 这样可以得到cid或者slug*/
        if (!($post instanceof Widget_Archive) || !$post->have() || !$post->is('single')) {
            return new IXR_Error(33, _t('这个目标地址不存在'));
        }

        if ($post) {
            /** 检查是否可以ping*/
            if ($post->allowPing) {

                /** 现在可以ping了，但是还得检查下这个pingback是否已经存在了*/
                $pingNum = $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')->where(
                        'table.comments.cid = ? AND table.comments.url = ? AND table.comments.type <> ?',
                        $post->cid,
                        $source,
                        'comment'
                    ))->num;

                if ($pingNum <= 0) {
                    /** 检查源地址是否存在*/
                    if (!($http = Typecho_Http_Client::get())) {
                        return new IXR_Error(16, _t('源地址服务器错误'));
                    }

                    try {

                        $http->setTimeout(5)->send($source);
                        $response = $http->getResponseBody();

                        if (200 == $http->getResponseStatus()) {

                            if (!$http->getResponseHeader('x-pingback')) {
                                preg_match_all("/<link[^>]*rel=[\"']([^\"']*)[\"'][^>]*href=[\"']([^\"']*)[\"'][^>]*>/i", $response, $out);
                                if (!isset($out[1]['pingback'])) {
                                    return new IXR_Error(50, _t('源地址不支持PingBack'));
                                }
                            }
                        } else {
                            return new IXR_Error(16, _t('源地址服务器错误'));
                        }
                    } catch (Exception $e) {
                        return new IXR_Error(16, _t('源地址服务器错误'));
                    }

                    /** 现在开始插入以及邮件提示了 $response就是第一行请求时返回的数组*/
                    preg_match("/\<title\>([^<]*?)\<\/title\\>/is", $response, $matchTitle);
                    $finalTitle = Typecho_Common::removeXSS(trim(strip_tags($matchTitle[1])));

                    /** 干掉html tag，只留下<a>*/
                    $text = Typecho_Common::stripTags($response, '<a href="">');

                    /** 此处将$target quote,留着后面用*/
                    $pregLink = preg_quote($target);

                    /** 找出含有target链接的最长的一行作为$finalText*/
                    $finalText = '';
                    $lines = explode("\n", $text);

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (NULL != $line) {
                            if (preg_match("|<a[^>]*href=[\"']{$pregLink}[\"'][^>]*>(.*?)</a>|", $line)) {
                                if (strlen($line) > strlen($finalText)) {
                                    /** <a>也要干掉，*/
                                    $finalText = Typecho_Common::stripTags($line);
                                }
                            }
                        }
                    }

                    /** 截取一段字*/
                    if (NULL == trim($finalText)) {
                        return new IXR_Error('17', _t('源地址中不包括目标地址'));
                    }

                    $finalText = '[...]' . Typecho_Common::subStr($finalText, 0, 200, '') . '[...]';

                    $pingback = array(
                        'cid' => $post->cid,
                        'created' => $this->options->time,
                        'agent' => $this->request->getAgent(),
                        'ip' => $this->request->getIp(),
                        'author' => Typecho_Common::subStr($finalTitle, 0, 150, '...'),
                        'url' => Typecho_Common::safeUrl($source),
                        'text' => $finalText,
                        'ownerId' => $post->author->uid,
                        'type' => 'pingback',
                        'status' => $this->options->commentsRequireModeration ? 'waiting' : 'approved'
                    );

                    /** 加入plugin */
                    $pingback = $this->pluginHandle()->pingback($pingback, $post);

                    /** 执行插入*/
                    $insertId = $this->singletonWidget('Widget_Abstract_Comments')->insert($pingback);

                    /** 评论完成接口 */
                    $this->pluginHandle()->finishPingback($this);

                    return $insertId;

                    /** todo:发送邮件提示*/
                } else {
                    return new IXR_Error(48, _t('PingBack已经存在'));
                }
            } else {
                return new IXR_Error(49, _t('目标地址禁止Ping'));
            }
        } else {
            return new IXR_Error(33, _t('这个目标地址不存在'));
        }
    }

    /**
     * 回收变量
     *
     * @access public
     * @param string $methodName 方法
     * @return void
     */
    public function hookAfterCall($methodName)
    {
        if (!empty($this->_usedWidgetNameList)) {
            foreach ($this->_usedWidgetNameList as $key => $widgetName) {
                $this->destory($widgetName);
                unset($this->_usedWidgetNameList[$key]);
            }
        }
    }

    /**
     * 入口执行方法
     *
     * @access public
     * @return void
     * @throws Typecho_Widget_Exception
     */
    public function action()
    {
        if (0 == $this->options->allowXmlRpc) {
            throw new Typecho_Widget_Exception(_t('请求的地址不存在'), 404);
        }

        if (isset($this->request->rsd)) {
            echo
            <<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<rsd version="1.0" xmlns="http://archipelago.phrasewise.com/rsd">
    <service>
        <engineName>Typecho</engineName>
        <engineLink>http://www.typecho.org/</engineLink>
        <homePageLink>{$this->options->siteUrl}</homePageLink>
        <apis>
            <api name="Typecho" blogID="1" preferred="true" apiLink="{$this->options->xmlRpcUrl}" />
        </apis>
    </service>
</rsd>
EOF;
        } else if (isset($this->request->wlw)) {
            echo
            <<<EOF
<?xml version="1.0" encoding="{$this->options->charset}"?>
<manifest xmlns="http://schemas.microsoft.com/wlw/manifest/weblog">
    <options>
        <supportsKeywords>Yes</supportsKeywords>
        <supportsFileUpload>Yes</supportsFileUpload>
        <supportsExtendedEntries>Yes</supportsExtendedEntries>
        <supportsCustomDate>Yes</supportsCustomDate>
        <supportsCategories>Yes</supportsCategories>

        <supportsCategoriesInline>Yes</supportsCategoriesInline>
        <supportsMultipleCategories>Yes</supportsMultipleCategories>
        <supportsHierarchicalCategories>Yes</supportsHierarchicalCategories>
        <supportsNewCategories>Yes</supportsNewCategories>
        <supportsNewCategoriesInline>Yes</supportsNewCategoriesInline>
        <supportsCommentPolicy>Yes</supportsCommentPolicy>

        <supportsPingPolicy>Yes</supportsPingPolicy>
        <supportsAuthor>Yes</supportsAuthor>
        <supportsSlug>Yes</supportsSlug>
        <supportsPassword>Yes</supportsPassword>
        <supportsExcerpt>Yes</supportsExcerpt>
        <supportsTrackbacks>Yes</supportsTrackbacks>

        <supportsPostAsDraft>Yes</supportsPostAsDraft>

        <supportsPages>Yes</supportsPages>
        <supportsPageParent>No</supportsPageParent>
        <supportsPageOrder>Yes</supportsPageOrder>
        <requiresXHTML>True</requiresXHTML>
        <supportsAutoUpdate>No</supportsAutoUpdate>

    </options>
</manifest>
EOF;
        } else {

            $api = array(
                /** Kraitnabo API */
                'kraitnabo.manifest.get' => array($this, 'NbGetManifest'),
                'kraitnabo.user.get' => array($this, 'NbGetUser'),
                'kraitnabo.stat.get' => array($this, 'NbGetStat'),
                'kraitnabo.post.new' => array($this, 'NbNewPost'),
                'kraitnabo.post.edit' => array($this, 'NbEditPost'),
                'kraitnabo.post.get' => array($this, 'NbGetPost'),
                'kraitnabo.post.delete' => array($this, 'NbDeletePost'),
                'kraitnabo.post.field' => array($this, 'NbFieldPost'),
                'kraitnabo.posts.get' => array($this, 'NbGetPosts'),
                'kraitnabo.page.new' => array($this, 'NbNewPage'),
                'kraitnabo.page.edit' => array($this, 'NbEditPage'),
                'kraitnabo.page.get' => array($this, 'NbGetPage'),
                'kraitnabo.pages.get' => array($this, 'NbGetPages'),
                'kraitnabo.comment.new' => array($this, 'NbNewComment'),
                'kraitnabo.comment.edit' => array($this, 'NbEditComment'),
                'kraitnabo.comment.delete' => array($this, 'NbDeleteComment'),
                'kraitnabo.comments.get' => array($this, 'NbGetComments'),
                'kraitnabo.category.new' => array($this, 'NbNewCategory'),
                'kraitnabo.category.edit' => array($this, 'NbEditCategory'),
                'kraitnabo.category.delete' => array($this, 'NbDeleteCategory'),
                'kraitnabo.categories.get' => array($this, 'NbGetCategories'),
                'kraitnabo.tags.get' => array($this, 'NbGetTags'),
                'kraitnabo.media.new' => array($this, 'NbNewMedia'),
                'kraitnabo.media.edit' => array($this, "NbEditMedia"),
                'kraitnabo.media.delete' => array($this, "NbDeleteMedia"),
                'kraitnabo.medias.get' => array($this, 'NbGetMedias'),
                'kraitnabo.medias.clear' => array($this, "NbClearMedias"),
                'kraitnabo.plugin.replace' => array($this, 'NbPluginReplace'),
                'kraitnabo.plugin.links' => array($this, 'NbPluginLinks'),
                'kraitnabo.plugin.dynamics' => array($this, 'NbPluginDynamics'),
                'kraitnabo.config.plugin' => array($this, 'NbConfigPlugin'),
                'kraitnabo.config.theme' => array($this, 'NbConfigTheme'),
                'kraitnabo.config.option' => array($this, 'NbConfigOption'),
                'kraitnabo.config.profile' => array($this, 'NbConfigProfile'),
                'kraitnabo.plugins.get' => array($this, 'NbGetPlugins'),
                'kraitnabo.plugins.set' => array($this, 'NbSetPlugins'),
                'kraitnabo.themes.get' => array($this, 'NbGetThemes'),
                'kraitnabo.themes.set' => array($this, 'NbSetThemes'),
                'kraitnabo.options.get' => array($this, 'NbGetOptions'),
                'kraitnabo.options.set' => array($this, 'NbSetOptions'),

                /** PingBack */
                'pingback.ping' => array($this, 'pingbackPing'),

                /** hook after */
                'hook.afterCall' => array($this, 'hookAfterCall'),
            );

            if (1 == $this->options->allowXmlRpc) {
                unset($api['pingback.ping']);
            }

            /** 直接把初始化放到这里 */
            new IXR_Server($api);
        }
    }
}