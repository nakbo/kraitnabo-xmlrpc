<?php /** @noinspection DuplicatedCode */
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
 * @author blankyao
 * @category typecho
 * @package Widget
 * @copyright Copyright (c) 2008 Typecho team (http://www.typecho.org)
 * @license GNU General Public License 2.0
 */
class Widget_XmlRpc extends Widget_Abstract_Contents implements Widget_Interface_Do
{
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
     * @param bool $run 是否执行
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

    public function GetManifest($version)
    {
        return array(
            "engineName" => "typecho",
            "versionCode" => 11,
            "versionName" => "2.0",
            "manifest" => $version
        );
    }


    /**
     * 检查权限
     *
     * @access public
     * @param $name
     * @param $password
     * @param string $level
     * @return bool
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function checkAccess($name, $password, $level = 'contributor')
    {
        /** 判断密码是明文还是MD5(32) */
        if (preg_match("/^[a-f0-9]{32}$/", $password)) {
            $user = $this->db->fetchRow($this->db->select()
                ->from('table.users')
                ->where((strpos($name, '@') ? 'mail' : 'name') . ' = ?', $name)
                ->limit(1));

            if (empty($user)) {
                return false;
            }

            if (hash_equals($password, md5($user['password']))) {
                /** 验证权限 */
                if (array_key_exists($level, $this->user->groups) && $this->user->groups[$this->user->group] <= $this->user->groups[$level]) {
                    /** 设置登录 */
                    $this->user->simpleLogin($user['uid']);
                    /** 更新最后活动时间  */
                    $this->db->query($this->db
                        ->update('table.users')
                        ->rows(array('activated' => Typecho_Widget::widget('Widget_Options')->time))
                        ->where('uid = ?', $user['uid']));
                    return true;
                } else {
                    $this->error = new IXR_Error(403, _t('权限不足'));
                    return false;
                }

            } else {
                $this->error = new IXR_Error(403, _t('无法登陆, 密码错误'));
                return false;
            }

        } else {
            if ($this->user->login($name, $password, true)) {
                /** 验证权限 */
                if ($this->user->pass($level, true)) {
                    $this->user->execute();
                    return true;
                } else {
                    $this->error = new IXR_Error(403, _t('权限不足'));
                    return false;
                }
            } else {
                $this->error = new IXR_Error(403, _t('无法登陆, 密码错误'));
                return false;
            }
        }

    }

    /**
     * 获取用户
     *
     * @access public
     * @param $blogId
     * @param $userName
     * @param $password
     * @return array|IXR_Error
     * @throws Typecho_Widget_Exception
     * @throws Typecho_Exception
     * @noinspection PhpUndefinedFieldInspection
     */
    public function GetUser($blogId, $userName, $password)
    {

        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        $struct = array(
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
        );

        return array(true, $struct);
    }

    /**
     * markdown
     * @param $text
     * @return false|string
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
     * @param $from
     * @param $type
     * @return int
     */
    public function GetCharacters($from, $type)
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
     * @param $cid
     * @return false|string
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
     * @param $comments
     * @param $struct
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
     * 常用统计
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Widget_Exception
     * @throws Typecho_Exception
     * @access public
     */
    public function GetStat($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }
        $statArray = array(
            "post" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'post')
                    ->where('authorId = ?', $blogId))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'post')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('authorId = ?', $blogId))->num,
                "waiting" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ? OR table.contents.type = ?', 'post', 'post_draft')
                    ->where('table.contents.status = ?', 'waiting')
                    ->where('authorId = ?', $blogId))->num,
                "draft" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'post_draft')
                    ->where('authorId = ?', $blogId))->num,
                "hidden" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'post')
                    ->where('table.contents.status = ?', 'hidden')
                    ->where('authorId = ?', $blogId))->num,
                "private" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'post')
                    ->where('table.contents.status = ?', 'private')
                    ->where('authorId = ?', $blogId))->num,
                "textSize" => $this->GetCharacters("table.contents", "post")
            ),
            "page" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'page')
                    ->where('authorId = ?', $blogId))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'page')
                    ->where('table.contents.status = ?', 'publish')
                    ->where('authorId = ?', $blogId))->num,
                "hidden" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'page')
                    ->where('table.contents.status = ?', 'hidden')
                    ->where('authorId = ?', $blogId))->num,
                "draft" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'page_draft')
                    ->where('authorId = ?', $blogId))->num,
                "textSize" => $this->GetCharacters("table.contents", "page")
            ),
            "comment" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments'))->num,
                "me" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('table.comments.authorId = ?', $blogId))->num,
                "publish" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('table.comments.status = ?', 'approved'))->num,
                "waiting" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('table.comments.status = ?', 'waiting'))->num,
                "spam" => $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))
                    ->from('table.comments')
                    ->where('table.comments.status = ?', 'spam'))->num,
                "textSize" => $this->GetCharacters("table.comments", "comment")
            ),
            "categories" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('table.metas.type = ?', 'category'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('table.metas.type = ?', 'category')
                    ->where('table.metas.count != ?', '0'))->num
            ),
            "tags" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('table.metas.type = ?', 'tag'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(mid)' => 'num'))
                    ->from('table.metas')
                    ->where('table.metas.type = ?', 'tag')
                    ->where('table.metas.count != ?', '0'))->num
            ),
            "medias" => array(
                "all" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'attachment'))->num,
                "archive" => $this->db->fetchObject($this->db->select(array('COUNT(cid)' => 'num'))
                    ->from('table.contents')
                    ->where('table.contents.type = ?', 'attachment')
                    ->where('table.contents.parent != ?', '0'))->num
            )
        );

        return array(true, $statArray);
    }

    /**
     * 获取指定id的post
     *
     * @param $blogId
     * @param string $userName
     * @param string $password
     * @param int $postId
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function GetPost($blogId, $userName, $password, $postId, $struct)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        $select = $this->db->select()->from('table.contents');
        $select->where('table.contents.authorId = ?', $blogId);
        $select->where('table.contents.cid = ?', $postId);

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
     * @param $blogId
     * @param string $userName
     * @param string $password
     * @param int $postId
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function GetPage($blogId, $userName, $password, $postId, $struct)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }
        return $this->GetPost($blogId, $userName, $password, $postId, $struct);
    }

    /**
     * 获取文章
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function GetPosts($blogId, $userName, $password, $struct)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        $status = empty($struct['status']) ? "all" : $struct['status'];
        $type = isset($struct['type']) && 'page' == $struct['type'] ? 'page' : 'post';

        $select = $this->db->select()->from('table.contents');
        $select->where('table.contents.authorId = ?', $blogId);

        switch ($status) {
            case "draft":
                $select->where('table.contents.type = ?', $type . '_draft');
                break;
            case "all":
                $select->where('table.contents.type = ?', $type);
                break;
            default:
                $select->where('table.contents.type = ?', $type);
                $select->where('table.contents.status = ?', $status);
        }

        if (!empty($struct['keywords'])) {
            $select->where('table.contents.title LIKE ?', '%' . $struct['keywords'] . '%');
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
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function GetPages($blogId, $userName, $password, $struct)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }
        $struct['type'] = "page";
        return $this->GetPosts($blogId, $userName, $password, $struct);
    }

    /**
     * 撰写文章
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection DuplicatedCode
     */
    public function NewPost($blogId, $userName, $password, $content)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
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

        if (isset($content['fields']) && is_array($content['fields'])) {
            $input['fields'] = $content['fields'];
        }

        if (!empty($content['categories']) && is_array($content['categories'])) {
            foreach ($content['categories'] as $category) {
                if (!$this->db->fetchRow($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category))) {
                    $this->NewCategory($blogId, $userName, $password, array('name' => $category));
                }

                $input['category'][] = $this->db->fetchObject($this->db->select('mid')
                    ->from('table.metas')->where('type = ? AND name = ?', 'category', $category)
                    ->limit(1))->mid;
            }
        }

        $input['allowComment'] = (isset($content['allow_comments']) && (1 == $content['allow_comments']
                || 'open' == $content['allow_comments'])) ? 1 : ((isset($content['allow_comments']) && (0 == $content['allow_comments']
                || 'closed' == $content['allow_comments'])) ? 0 : $this->options->defaultAllowComment);

        $input['allowPing'] = (isset($content['allow_pings']) && (1 == $content['allow_pings']
                || 'open' == $content['allow_pings'])) ? 1 : ((isset($content['allow_pings']) && (0 == $content['allow_pings']
                || 'closed' == $content['allow_pings'])) ? 0 : $this->options->defaultAllowPing);

        $input['allowFeed'] = $this->options->defaultAllowFeed;
        $input['do'] = $content["publish"] ? 'publish' : 'save';
        $input['markdown'] = $this->options->xmlrpcMarkdown;

        /** 调整状态 */
        if (isset($content["status"])) {
            $status = $content["status"];
        } else {
            $status = "publish";
        }
        $input['visibility'] = isset($content["visibility"]) ? $content["visibility"] : $status;
        if ('publish' == $status || 'waiting' == $status || 'private' == $status || 'hidden' == $status) {
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
            /** 插入 */
            if ('page' == $type) {
                $this->singletonWidget('Widget_Contents_Page_Edit', NULL, $input, false)->action();
            } else {
                $this->singletonWidget('Widget_Contents_Post_Edit', NULL, $input, false)->action();
            }
            return array(true, $this->singletonWidget('Widget_Notice')->getHighlightId());
        } catch (Typecho_Widget_Exception $e) {
            return array(false, new IXR_Error($e->getCode(), $e->getMessage()));
        }
    }

    /**
     * 编辑文章
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param array $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function EditPost($postId, $userName, $password, $content)
    {
        return $this->NewPost($postId, $userName, $password, $content);
    }

    /**
     * 删除文章
     * @param mixed $blogId
     * @param mixed $userName
     * @param mixed $password
     * @param $postId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function DeletePost($blogId, $userName, $password, $postId)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function GetComments($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        $select = $this->db->select('table.comments.coid',
            'table.comments.cid',
            'table.comments.created',
            'table.comments.author',
            'table.comments.authorId',
            'table.comments.ownerId',
            'table.comments.mail',
            'table.comments.url',
            'table.comments.ip',
            'table.comments.agent',
            'table.comments.text',
            'table.comments.type',
            'table.comments.status',
            'table.comments.parent',
            'table.contents.title'
        )->from('table.comments')->join('table.contents', 'table.comments.cid = table.contents.cid', Typecho_Db::LEFT_JOIN);
        $select->where('table.comments.ownerId = ?', $blogId);

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
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function GetAlarmComments($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }
        if (empty($struct['lastTime'])) {
            return new IXR_Error(403, _t('缺少参数'));
        }
        if (empty($struct['number'])) {
            $query = $this->db->select()->from('table.comments')->where('table.comments.authorId != ?', $blogId)->where('created >= ?', intval($struct['lastTime']));
            $result = $this->db->fetchAll($query);
        } else {
            $query = $this->db->select(array('COUNT(coid)' => 'num'))->from('table.comments')->where('table.comments.authorId != ?', $blogId)->where('created >= ?', intval($struct['lastTime']));
            $result = $this->db->fetchObject($query)->num;
        }
        return array(true, $result);
    }

    /**
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function GetAlarmMessages($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }
        if (empty($struct['lastTime'])) {
            return new IXR_Error(403, _t('缺少参数'));
        }

        /** @noinspection PhpUndefinedFieldInspection */
        if (!isset($this->options->plugins['activated']['Messages'])) {
            return new IXR_Error(403, "没有启用 messages 插件");
        }

        if (empty($struct['number'])) {
            $query = $this->db->select()->from('table.messages')->where('table.messages.authorId = ?', $blogId)->where('created >= ?', intval($struct['lastTime']));
            $result = $this->db->fetchAll($query);
        } else {
            $query = $this->db->select(array('COUNT(mid)' => 'num'))->from('table.messages')->where('table.messages.authorId = ?', $blogId)->where('created >= ?', intval($struct['lastTime']));
            $result = $this->db->fetchObject($query)->num;
        }
        return array(true, $result);
    }

    /**
     * 删除评论
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function DeleteComment($blogId, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $path
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection PhpUnhandledExceptionInspection
     */
    public function NewComment($blogId, $userName, $password, $path, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function GetComment($blogId, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        $comments = $this->singletonWidget('Widget_Comments_Edit', NULL, 'do=get&coid=' . intval($commentId), false);

        if (!$comments->have()) {
            return new IXR_Error(404, _t('评论不存在'));
        }

        if (!$comments->commentIsWriteable()) {
            return new IXR_Error(403, _t('没有获取评论的权限'));
        }

        $commentsStruct = $this->commonCommentsStruct($comments, null);

        return array(true, $commentsStruct);
    }

    /**
     * 编辑评论
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @param array $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function EditComment($blogId, $userName, $password, $commentId, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
        } elseif (isset($struct['created_gmt'])) {
            $input['created'] = $struct['created_gmt']->getTimestamp() - $this->options->timezone + $this->options->serverTimezone;
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
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $data
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     * @noinspection PhpUndefinedMethodInspection
     */
    public function NewMedia($blogId, $userName, $password, $data)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
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
     * 获取所有的分类
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function GetCategories($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param $category
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function NewCategory($blogId, $userName, $password, $category)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param $category
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function EditCategory($blogId, $userName, $password, $category)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $categoryId
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function DeleteCategory($blogId, $userName, $password, $categoryId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
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
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function GetTags($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * 编辑文章
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param $content
     * @return array|IXR_Error|void
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @access public
     */
    public function EditPage($postId, $userName, $password, $content)
    {
        $content['type'] = 'page';
        return $this->NewPost(1, $userName, $password, $content);
    }


    /**
     * 获取系统选项
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function GetOptions($blogId, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = array();
        foreach ($options as $option) {
            $select = $this->db->select()->from('table.options')
                ->where('name = ?', $option)
                ->where('user = ?', $blogId - 1);

            $os = $this->db->fetchAll($select);
            if (!empty($os)) {
                foreach ($os as $o) {
                    $struct[] = array(
                        $o['name'],
                        $o['value']
                    );
                }
            }
        }

        return array(true, $struct);
    }

    /**
     * 设置系统选项
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function SetOptions($blogId, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
            return $this->error;
        }

        $struct = array();
        foreach ($options as $object) {
            $select = $this->db->select()->from('table.options')
                ->where('name = ?', $object[0])
                ->where('user = ?', $blogId - 1);
            $os = $this->db->fetchAll($select);
            if (!empty($os)) {
                foreach ($os as $o) {
                    if ($this->db->query($this->db->update('table.options')
                            ->rows(array('value' => $object[1]))
                            ->where('user = ?', $blogId - 1)
                            ->where('name = ?', $o['name'])) > 0) {
                        $struct[] = array(
                            $o['name'],
                            $object[1]
                        );
                    }
                }
            }
        }

        return array(true, $struct);
    }

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
     * 获取媒体文件
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function GetMedias($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function ClearMedias($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function DeleteMedia($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function EditMedia($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     * @noinspection SqlNoDataSourceInspection
     * @noinspection SqlDialectInspection
     * @noinspection PhpSingleStatementWithBracesInspection
     */
    public function PluginReplace($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function PluginDynamics($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
                $dynamic['authorId'] = $blogId;
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
                    ->where('table.dynamics.authorId = ?', $blogId);

                $pageSize = empty($struct['number']) ? 10 : abs(intval($struct['number']));
                $currentPage = empty($struct['offset']) ? 1 : ceil(abs(intval($struct['offset'])) / $pageSize);
                $select->order('table.dynamics.created', Typecho_Db::SORT_DESC)
                    ->page($currentPage, $pageSize);

                $dynamics = $this->db->fetchAll($select);
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function PluginMessages($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        if (!isset($this->options->plugins['activated']['Messages'])) {
            return new IXR_Error(403, "没有启用 messages 插件");
        }

        if (!is_array($struct)) {
            return new IXR_Error(403, "struct不是一个数组对象");
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if ($struct['method'] == "delete") {
            $mids = $struct['mids'];
            if (!is_array($mids)) {
                return new IXR_Error(403, "mids 不是一个数组对象");
            }
            $deleteCount = 0;
            foreach ($mids as $mid) {
                if ($this->db->query($this->db->delete('table.messages')->where('mid = ?', $mid))) {
                    $deleteCount++;
                }
            }
            return array(true, $deleteCount);
        } else if ($struct['method'] == "destroy") {
            $this->db->query($this->db->delete('table.messages')
                ->where('destroy <?', time())
                ->where('authorId =? ', $blogId)
            );
            return array(true, null);
        } else {
            try {
                $select = $this->db->select()->from('table.messages')
                    ->where('table.messages.authorId = ?', $blogId);

                $pageSize = empty($struct['number']) ? 10 : abs(intval($struct['number']));
                $currentPage = empty($struct['offset']) ? 1 : ceil(abs(intval($struct['offset'])) / $pageSize);
                $select->order('table.messages.created', Typecho_Db::SORT_DESC)
                    ->page($currentPage, $pageSize);

                $messages = $this->db->fetchAll($select);
                return array(true, $messages);
            } catch (Typecho_Widget_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        }
    }

    /**
     * 友情链接管理 - 插件
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function PluginLinks($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
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
                $select->order('table.links.order', Typecho_Db::SORT_ASC)
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
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function ConfigPlugins($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        if (!isset($struct['pluginName'])) {
            return new IXR_Error(403, "没有设定插件名字");
        }

        /** @noinspection PhpUndefinedFieldInspection */
        if (!isset($this->options->plugins['activated']{$struct['pluginName']})) {
            return new IXR_Error(403, "没有启用插件");
        }

        if ($struct['method'] == "edit") {
            if (!isset($struct['settings']) || !is_array($struct['settings'])) {
                return new IXR_Error(403, "settings 不规范");
            }

            if (empty($struct['settings'])) {
                return new IXR_Error(403, "settings 不规范");
            }

            try {
                $this->singletonWidget('Widget_Plugins_Edit', NULL, NULL, false)->configPlugin(
                    $struct['pluginName'],
                    $struct['settings']
                );
                return array(true, null);
            } catch (Typecho_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        } else {
            $select = $this->db->select()->from('table.options')
                ->where('name = ?', 'plugin:' . $struct['pluginName']);

            $options = $this->db->fetchAll($select);
            $data = array();
            foreach ($options as $option) {
                $data[] = unserialize($option['value']);
            }
            return array(true, $data);
        }

    }

    /**
     * 主题配置管理
     *
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param $struct
     * @return array|IXR_Error
     * @throws Typecho_Exception
     * @throws Typecho_Widget_Exception
     */
    public function ConfigTheme($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, "administrator")) {
            return $this->error;
        }

        if (!isset($struct['method'])) {
            return new IXR_Error(403, "没有设定模式");
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $theme = $this->options->theme;
        $select = $this->db->select()->from('table.options')
            ->where('name = ?', 'theme:' . $theme);

        $options = $this->db->fetchAll($select);

        if ($struct['method'] == "edit") {
            if (!isset($struct['settings']) || !is_array($struct['settings'])) {
                return new IXR_Error(403, "settings 不规范");
            }

            if (empty($struct['settings'])) {
                return new IXR_Error(403, "settings 不规范");
            }
            try {
                foreach ($options as $option) {
                    $value = unserialize($option['value']);
                    $value = array_merge($value, $struct['settings']);

                    $this->db->query($this->db->update('table.options')
                        ->rows(array('value' => serialize($value)))
                        ->where('name = ?', 'theme:' . $theme)
                        ->where('user = ?', $option['user']));
                }
                return array(true, null);
            } catch (Typecho_Exception $e) {
                return new IXR_Error($e->getCode(), $e->getMessage());
            }
        } else {
            $data = array();
            foreach ($options as $option) {
                $data[] = unserialize($option['value']);
            }
            $data[] = array(
                "name" => $theme
            );
            return array(true, $data);
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
                return IXR_Error(49, _t('目标地址禁止Ping'));
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
                /** Typecho API */
                'typecho.getManifest' => array($this, 'GetManifest'),
                'typecho.getUser' => array($this, 'GetUser'),
                'typecho.getStat' => array($this, 'GetStat'),
                'typecho.newPost' => array($this, 'NewPost'),
                'typecho.editPost' => array($this, 'EditPost'),
                'typecho.getPost' => array($this, 'GetPost'),
                'typecho.deletePost' => array($this, 'DeletePost'),
                'typecho.getPosts' => array($this, 'GetPosts'),
                'typecho.getPage' => array($this, 'GetPage'),
                'typecho.editPage' => array($this, 'EditPage'),
                'typecho.getPages' => array($this, 'GetPages'),
                'typecho.newComment' => array($this, 'NewComment'),
                'typecho.getComments' => array($this, 'GetComments'),
                'typecho.editComment' => array($this, 'EditComment'),
                'typecho.deleteComment' => array($this, 'DeleteComment'),
                'typecho.getCategories' => array($this, 'GetCategories'),
                'typecho.newCategory' => array($this, 'NewCategory'),
                'typecho.editCategory' => array($this, 'EditCategory'),
                'typecho.deleteCategory' => array($this, 'DeleteCategory'),
                'typecho.getOptions' => array($this, 'GetOptions'),
                'typecho.setOptions' => array($this, 'SetOptions'),
                'typecho.getTags' => array($this, 'GetTags'),
                'typecho.getAlarmComments' => array($this, 'GetAlarmComments'),
                'typecho.newMedia' => array($this, 'NewMedia'),
                'typecho.getMedias' => array($this, 'GetMedias'),
                'typecho.editMedia' => array($this, "EditMedia"),
                'typecho.deleteMedia' => array($this, "DeleteMedia"),
                'typecho.clearMedias' => array($this, "ClearMedias"),
                'typecho.pluginReplace' => array($this, 'PluginReplace'),
                'typecho.pluginLinks' => array($this, 'PluginLinks'),
                'typecho.pluginDynamics' => array($this, 'PluginDynamics'),
                'typecho.pluginMessages' => array($this, 'PluginMessages'),
                'typecho.configPlugins' => array($this, 'ConfigPlugins'),
                'typecho.configTheme' => array($this, 'ConfigTheme'),
                'typecho.getAlarmMessages' => array($this, 'GetAlarmMessages'),

                /** PingBack */
                'pingback.ping' => array($this, 'pingbackPing'),
                // 'pingback.extensions.getPingbacks' => array($this,'pingbackExtensionsGetPingbacks'),

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
