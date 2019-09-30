<?php
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
     * wordpress风格的系统选项
     * 
     * @access private
     * @var array
     */
    private $_wpOptions;
    
    /**
     * 已经使用过的组件列表
     * 
     * @access private
     * @var array
     */
    private $_usedWidgetNameList = array();
    
    /**
     * 获取扩展字段
     * 
     * @access private
     * @param Widget_Abstract_Contents $content
     * @return array
     */
    private function getPostExtended(Widget_Abstract_Contents $content)
    {
        //根据客户端显示来判断是否显示html代码
        $agent = $this->request->getAgent();
        $text = '';
        
        switch (true) {
            case false !== strpos($agent, 'wp-iphone'):   // wordpress iphone客户端
            case false !== strpos($agent, 'wp-blackberry'):  // 黑莓
            case false !== strpos($agent, 'wp-andriod'):  // andriod
            case false !== strpos($agent, 'plain-text'):  // 这是预留给第三方开发者的接口, 用于强行调用非所见即所得数据
            case $this->options->xmlrpcMarkdown:
                $text = $content->text;
                break;
            default:
                $text = $content->content;
                break;
        }
    
        $post = explode('<!--more-->', $text, 2);
        return array(
            $this->options->xmlrpcMarkdown? $post[0] : Typecho_Common::fixHtml($post[0]),
            isset($post[1]) ? Typecho_Common::fixHtml($post[1]) : NULL
        );
    }
    
    /**
     * 将typecho的状态类型转换为wordperss的风格
     * 
     * @access private
     * @param string $status typecho的状态
     * @param string $type 内容类型
     * @return string
     */
    private function typechoToWordpressStatus($status, $type = 'post')
    {
        if ('post' == $type) {
            /** 文章状态 */
            switch ($status) {
                case 'waiting':
                    return 'pending';
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } else if ('page' == $type) {
            switch ($status) {
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } else if ('comment' == $type) {
            switch ($status) {
                case 'publish':
                case 'approved':
                    return 'approve';
                case 'waiting':
                    return 'hold';
                case 'spam':
                    return $status;
                default:
                    return 'approve';
            }
        }
        
        return '';
    }
    
    /**
     * 将wordpress的状态类型转换为typecho的风格
     * 
     * @access private
     * @param string $status wordpress的状态
     * @param string $type 内容类型
     * @return string
     */
    private function wordpressToTypechoStatus($status, $type = 'post')
    {
        if ('post' == $type) {
            /** 文章状态 */
            switch ($status) {
                case 'pending':
                    return 'waiting';
                case 'publish':
                case 'draft':
                case 'private':
                case 'waiting':
                    return $status;
                default:
                    return 'publish';
            }
        } else if ('page' == $type) {
            switch ($status) {
                case 'publish':
                case 'draft':
                case 'private':
                    return $status;
                default:
                    return 'publish';
            }
        } else if ('comment' == $type) {
            switch ($status) {
                case 'approve':
                case 'publish':
                case 'approved':
                    return 'approved';
                case 'hold':
                case 'waiting':
                    return 'waiting';
                case 'spam':
                    return $status;
                default:
                    return 'approved';
            }
        }
        
        return '';
    }
    
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
     * @param boolen $run 是否执行
     * @return void
     */
    public function execute($run = false)
    {
        if ($run) {
            parent::execute();
        }

        // 临时保护模块
        $this->security->enable(false);
        
        $this->_wpOptions = array(
			// Read only options
			'software_name'		=> array(
				'desc'			=> _t( '软件名称' ),
				'readonly'		=> true,
				'value'			=> $this->options->software
			),
			'software_version'	=> array(
				'desc'			=> _t( '软件版本' ),
				'readonly'		=> true,
				'value'			=> $this->options->version
			),
			'blog_url'			=> array(
				'desc'			=> _t( '博客地址' ),
				'readonly'		=> true,
				'option'		=> 'siteUrl'
			),
            'home_url'          => array(
                'desc'          => _t( '博客首页地址' ),
                'readonly'      => true,
                'option'        => 'siteUrl'
            ),
            'login_url'         => array(
                'desc'          => _t( '登录地址' ),
                'readonly'      => true,
                'value'         => $this->options->siteUrl.'admin/login.php'
            ),
             'admin_url'        => array(
                'desc'          => _t( '管理区域的地址' ),
                'readonly'      => true,
                'value'         => $this->options->siteUrl.'admin/'
            ),

            'post_thumbnail'    => array(
                'desc'          => _t( '文章缩略图' ),
                'readonly'      => true,
                'value'         => false
            ),

			// Updatable options
			'time_zone'			=> array(
				'desc'			=> _t( '时区' ),
				'readonly'		=> false,
				'option'		=> 'timezone'
			),
			'blog_title'		=> array(
				'desc'			=> _t( '博客标题' ),
				'readonly'		=> false,
				'option'			=> 'title'
			),
			'blog_tagline'		=> array(
				'desc'			=> _t( '博客关键字' ),
				'readonly'		=> false,
				'option'		=> 'description'
			),
			'date_format'		=> array(
				'desc'			=> _t( '日期格式' ),
				'readonly'		=> false,
				'option'		=> 'postDateFormat'
			),
			'time_format'		=> array(
				'desc'			=> _t( '时间格式' ),
				'readonly'		=> false,
				'option'		=> 'postDateFormat'
			),
			'users_can_register'	=> array(
				'desc'			=> _t( '是否允许注册' ),
				'readonly'		=> false,
				'option'		=> 'allowRegister'
			)
		);
    }

    /**
     * 检查权限
     *
     * @access public
     * @return void
     */
    public function checkAccess($name, $password, $level = 'contributor')
    {
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

    /**
     * 获取pageId指定的page
     * about wp xmlrpc api, you can see http://codex.wordpress.org/XML-RPC
     *
     * @param int $blogId
     * @param int $pageId
     * @param string $userName
     * @param string $password
     * @access public
     * @return struct $pageStruct
     */
    public function wpGetPage($blogId, $pageId, $userName, $password)
    {
        /** 检查权限 */
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        /** 获取页面 */
        try {
            /** 由于Widget_Contents_Page_Edit是从request中获取参数, 因此我们需要强行设置flush一下request */
            /** widget方法的第三个参数可以指定强行转换传入此widget的request参数 */
            /** 此组件会进行复杂的权限检测 */
            $page = $this->singletonWidget('Widget_Contents_Page_Edit', NULL, "cid={$pageId}");
        } catch (Typecho_Widget_Exception $e) {
            /** 截获可能会抛出的异常(参见 Widget_Contents_Page_Edit 的 execute 方法) */
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        /** 对文章内容做截取处理，以获得description和text_more*/
        list($excerpt, $more) = $this->getPostExtended($page);

        $pageStruct = array(
            'dateCreated'   => new IXR_Date($this->options->timezone + $page->created),
            'userid'        => $page->authorId,
            'page_id'       => $page->cid,
            'page_status'   => $this->typechoToWordpressStatus($page->status, 'page'),
            'description'   => $excerpt,
            'title'         => $page->title,
            'link'          => $page->permalink,
            'permaLink'     => $page->permalink,
            'categories'    => $page->categories,
            'excerpt'       => $page->description,
            'text_more'     => $more,
            'mt_allow_comments' => intval($page->allowComment),
            'mt_allow_pings'    => intval($page->allowPing),
            'wp_slug'       => $page->slug,
            'wp_password'   => $page->password,
            'wp_author'     => $page->author->name,
            'wp_page_parent_id' => '0',
            'wp_page_parent_title' => '',
            'wp_page_order' => $page->order,     //meta是描述字段, 在page时表示顺序
            'wp_author_id'  => $page->authorId,
            'wp_author_display_name' => $page->author->screenName,
            'date_created_gmt'  => new IXR_Date($page->created),
            'custom_fields'     => array(),
            'wp_page_template'  =>  $page->template
        );

        return $pageStruct;
    }

    /**
     * 获取所有的page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return array(contains $pageStruct)
     */
    public function wpGetPages($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        /** 过滤type为page的contents */
        /** 同样需要flush一下, 需要取出所有status的页面 */
        $pages = $this->singletonWidget('Widget_Contents_Page_Admin', NULL, 'status=all');

        /** 初始化要返回的数据结构 */
        $pageStructs = array();

        while ($pages->next()) {
            /** 对文章内容做截取处理，以获得description和text_more*/
            list($excerpt, $more) = $this->getPostExtended($pages);
            $pageStructs[] = array(
                'dateCreated'            => new IXR_Date($this->options->timezone + $pages->created),
                'userid'                 => $pages->authorId,
                'page_id'                => intval($pages->cid),
                'page_status'            => $this->typechoToWordpressStatus(($pages->hasSaved || 'page_draft' == $pages->type) ? 'draft' : $pages->status, 'page'),
                'description'            => $excerpt,
                'title'                  => $pages->title,
                'link'                   => $pages->permalink,
                'permaLink'              => $pages->permalink,
                'categories'             => $pages->categories,
                'excerpt'                => $pages->description,
                'text_more'              => $more,
                'mt_allow_comments'      => intval($pages->allowComment),
                'mt_allow_pings'         => intval($pages->allowPing),
                'wp_slug'                => $pages->slug,
                'wp_password'            => $pages->password,
                'wp_author'              => $pages->author->name,
                'wp_page_parent_id'      => 0,
                'wp_page_parent_title'   => '',
                'wp_page_order'          => intval($pages->order),     //meta是描述字段, 在page时表示顺序
                'wp_author_id'           => $pages->authorId,
                'wp_author_display_name' => $pages->author->screenName,
                'date_created_gmt'       => new IXR_Date($pages->created),
                'custom_fields'          => array(),
                'wp_page_template'       =>  $pages->template
            );
        }

        return $pageStructs;
    }

    /**
     * 撰写一个新page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param struct $content
     * @param bool $publish
     * @access public
     * @return void
     */
    public function wpNewPage($blogId, $userName, $password, $content, $publish)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }
        $content['post_type'] = 'page';
        $this->mwNewPost($blogId, $userName, $password, $content, $publish);
    }

    /**
     * 删除pageId指定的page
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $pageId
     * @access public
     * @return bool
     */
    public function wpDeletePage($blogId, $userName, $password, $pageId)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }

        /** 删除页面 */
        try {
            /** 此组件会进行复杂的权限检测 */
            $this->singletonWidget('Widget_Contents_Page_Edit', NULL, "cid={$pageId}", false)->deletePage();
        } catch (Typecho_Widget_Exception $e) {
            /** 截获可能会抛出的异常(参见 Widget_Contents_Page_Edit 的 execute 方法) */
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        return true;
    }

    /**
     * 编辑pageId指定的page
     *
     * @param int $blogId
     * @param int $pageId
     * @param string $userName
     * @param string $password
     * @param struct $content
     * @param bool $publish
     * @access public
     * @return bool
     */
    public function wpEditPage($blogId, $pageId, $userName, $password, $content, $publish)
    {
        $content['post_type'] = 'page';
        $this->mwEditPost($pageId, $userName, $password, $content, $publish);
    }


    /**
     * 编辑postId指定的post 
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param int $postId
     * @param struct $content
     * @access public
     * @return bool
     */
    public function wpEditPost($blogId, $userName, $password, $postId, $content)
    {

        $post = $this->singletonWidget('Widget_Archive', 'type=single', 'cid=' . $postId, false);
        if ($post->type == 'attachment') {
            $attachment['title'] = $content['post_title'];
            $attachment['slug'] = $content['post_excerpt'];

            $text = unserialize($post->text);
            $text['description'] = $content['description'];

            $attachment['text'] = serialize($text);

            /** 更新数据 */
            $updateRows = $this->update($attachment, $this->db->sql()->where('cid = ?', $postId));
            return true;
        }
        return $this->mwEditPost($blogId, $postId, $userName, $password, $content);
    }

    /**
     * 获取page列表，没有wpGetPages获得的详细
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return array
     */
    public function wpGetPageList($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return ($this->error);
        }
        $pages = $this->singletonWidget('Widget_Contents_Page_Admin', NULL, 'status=all');
        /**初始化*/
        $pageStructs = array();

        while ($pages->next()) {
            $pageStructs[] = array(
                'dateCreated'       => new IXR_Date($this->options->timezone + $pages->created),
                'date_created_gmt'  => new IXR_Date($this->options->timezone + $pages->created),
                'page_id'           => $pages->cid,
                'page_title'        => $pages->title,
                'page_parent_id'    => '0',
            );
        }

        return $pageStructs;
    }

    /**
     * 获得一个由blog所有作者的信息组成的数组
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @access public
     * @return struct
     */
    public function wpGetAuthors($blogId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return ($this->error);
        }

        /** 构建查询*/
        $select = $this->db->select('table.users.uid', 'table.users.name', 'table.users.screenName')->from('table.users');
        $authors = $this->db->fetchAll($select);

        $authorStructs = array();
        foreach ($authors as $author) {
            $authorStructs[] = array(
                'user_id'       => $author['uid'],
                'user_login'    => $author['name'],
                'display_name'  => $author['screenName']
            );
        }

        return $authorStructs;
    }

    /**
     * 添加一个新的分类
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param struct $category
     * @access public
     * @return void
     */
    public function wpNewCategory($blogId, $userName, $password, $category)
    {
        if (!$this->checkAccess($userName, $password)) {
            return ($this->error);
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
             return $categoryWidget->mid;
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        return new IXR_Error(403, _t('无法添加分类'));
    }

    /**
     * 获取由给定的string开头的链接组成的数组
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param string $category
     * @param int $max_results
     * @access public
     * @return array
     */
    public function wpSuggestCategories($blogId, $userName, $password, $category, $max_results)
    {
        if (!$this->checkAccess($userName, $password)) {
            return ($this->error);
        }

        $meta = $this->singletonWidget('Widget_Abstract_Metas');

        /** 构造出查询语句并且查询*/
        $key = Typecho_Common::filterSearchQuery($category);
        $key = '%' . $key . '%';
        $select = $meta->select()->where('table.metas.type = ? AND (table.metas.name LIKE ? OR slug LIKE ?)', 'category', $key, $key);

        /** 不要category push到contents的容器中 */
        $categories = $this->db->fetchAll($select);

        /** 初始化categorise数组*/
        $categoryStructs = array();
        foreach ($categories as $category) {
            $categoryStructs[] = array(
                'category_id'   => $category['mid'],
                'category_name' => $category['name'],
            );
        }

        return $categoryStructs;
    }
    
    /**
     * 获取用户
     * 
     * @access public
     * @param string $userName 用户名
     * @param string $password 密码
     * @return array
     */
    public function wpGetUsersBlogs($userName, $password)
    {

        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $struct = array();
        $struct[] = array(
            'isAdmin'   => $this->user->pass('administrator', true),
            'url'       => $this->options->siteUrl,
            'blogid'    => '1',
            'blogName'  => $this->options->title,
            'xmlrpc'    => $this->options->xmlRpcUrl
        );
        return $struct;
    }

        /**
     * 获取用户
     * 
     * @access public
     * @param string $userName 用户名
     * @param string $password 密码
     * @return array
     */
    public function wpGetProfile($blogId, $userName, $password)
    {

        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        $struct = array(
            'user_id'       => $this->user->uid,
            'username'      => $this->user->name,
            'first_name'    => '',
            'last_name'     => '',
            'registered'    => new IXR_Date($this->options->timezone +  $this->user->created),
            'bio'           => '',
            'email'         => $this->user->mail,
            'nickname'      => $this->user->screenName,
            'url'           => $this->user->url,
            'display_name'  => $this->user->screenName,
            'roles'         => $this->user->group
        );
        return $struct;
    }
    
    /**
     * 获取标签列表
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetTags($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        $struct = array();
        $tags = $this->singletonWidget('Widget_Metas_Tag_Cloud');
        
        while ($tags->next()) {
            $struct[] = array(
                'tag_id'    =>  $tags->mid,
                'name'      =>  $tags->name,
                'count'     =>  $tags->count,
                'slug'      =>  $tags->slug,
                'html_url'  =>  $tags->permalink,
                'rss_url'   =>  $tags->feedUrl
            );
        }
        
        return $struct;
    }
    
    /**
     * 删除分类
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $categoryId
     * @return array
     */
    public function wpDeleteCategory($blogId, $userName, $password, $categoryId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }
        
        try {
            $this->singletonWidget('Widget_Metas_Category_Edit', NULL, 'do=delete&mid=' . intval($categoryId), false);
            return true;
        } catch (Typecho_Exception $e) {
            return false;
        }
    }
    
    /**
     * 获取评论数目
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $postId
     * @return array
     */
    public function wpGetCommentCount($blogId, $userName, $password, $postId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        $stat = $this->singletonWidget('Widget_Stat', NULL, 'cid=' . intval($postId), false);
        
        return array(
            'approved' => $stat->currentPublishedCommentsNum,
            'awaiting_moderation' => $stat->currentWaitingCommentsNum,
            'spam' => $stat->currentSpamCommentsNum,
            'total_comments' => $stat->currentCommentsNum
        );
    }

    
    /**
     * 获取文章类型列表
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPostFormats($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        return array(
            'standard' => _t('标准')
        );
    }
    
    /**
     * 获取文章状态列表
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPostStatusList($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        return array(
            'draft'     =>  _t('草稿'),
            'pending'   =>  _t('待审核'),
            'publish'   =>  _t('已发布')
        );
    }
    
    /**
     * 获取页面状态列表
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPageStatusList($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }
        
        return array(
            'draft'     =>  _t('草稿'),
            'publish'   =>  _t('已发布')
        );
    }


    
    /**
     * 获取评论状态列表
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetCommentStatusList($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        return array(
            'hold'      =>  _t('待审核'),
            'approve'   =>  _t('显示'),
            'spam'      =>  _t('垃圾')
        );
    }
    
    /**
     * 获取页面模板
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @return array
     */
    public function wpGetPageTemplates($blogId, $userName, $password)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'editor')) {
            return $this->error;
        }
        
        $templates = array_flip($this->getTemplates());
        $templates['Default'] = '';
        
        return $templates;
    }
    
    /**
     * 获取系统选项
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array
     */
    public function wpGetOptions($blogId, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
            return $this->error;
        }
        
        $struct = array();
        if (empty($options)) {
            $options = array_keys($this->_wpOptions);
        }
        
        foreach ($options as $option) {
            if (isset($this->_wpOptions[$option])) {
                $struct[$option] = $this->_wpOptions[$option];
                if (isset($struct[$option]['option'])) {
                    $struct[$option]['value'] = $this->options->{$struct[$option]['option']};
                    unset($struct[$option]['option']);
                }
            }
        }
        
        return $struct;
    }
    
    /**
     * 设置系统选项
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $options
     * @return array
     */
    public function wpSetOptions($blogId, $userName, $password, $options = array())
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password, 'administrator')) {
            return $this->error;
        }
        
        $struct = array();
        foreach ($options as $option => $value) {
            if (isset($this->_wpOptions[$option])) {
                $struct[$option] = $this->_wpOptions[$option];
                if (isset($struct[$option]['option'])) {
                    $struct[$option]['value'] = $this->options->{$struct[$option]['option']};
                    unset($struct[$option]['option']);
                }
            
                if (!$this->_wpOptions[$option]['readonly'] && isset($this->_wpOptions[$option]['option'])) {
                    if ($this->db->query($this->db->update('table.options')
                    ->rows(array('value' => $value))
                    ->where('name = ?', $this->_wpOptions[$option]['option'])) > 0) {
                        $struct[$option]['value'] = $value;
                    }
                }
            }
        }
        
        return $struct;
    }
    
    /**
     * 获取评论
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @return array
     */
    public function wpGetComment($blogId, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        $comment = $this->singletonWidget('Widget_Comments_Edit', NULL, 'do=get&coid=' . intval($commentId), false);
        
        if (!$comment->have()) {
            return new IXR_Error(404, _t('评论不存在'));
        }
        
        if (!$comment->commentIsWriteable()) {
            return new IXR_Error(403, _t('没有获取评论的权限'));
        }
        
        return array(
            'date_created_gmt'		=> new IXR_Date($this->options->timezone + $comment->created),
			'user_id'				=> $comment->authorId,
			'comment_id'			=> $comment->coid,
			'parent'				=> $comment->parent,
			'status'				=> $this->typechoToWordpressStatus($comment->status, 'comment'),
			'content'				=> $comment->text,
			'link'					=> $comment->permalink,
			'post_id'				=> $comment->cid,
			'post_title'			=> $comment->title,
			'author'				=> $comment->author,
			'author_url'			=> $comment->url,
			'author_email'			=> $comment->mail,
			'author_ip'				=> $comment->ip,
			'type'					=> $comment->type
        );
    }
    
    /**
     * 获取评论列表
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param array $struct
     * @return array
     */
    public function wpGetComments($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        $input = array();
        if (!empty($struct['status'])) {
            $input['status'] = $this->wordpressToTypechoStatus($struct['status'], 'comment');
        } else {
            $input['__typecho_all_comments'] = 'on';
        }
        
        if (!empty($struct['post_id'])) {
            $input['cid'] = $struct['post_id'];
        }
        
        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = abs(intval($struct['number']));
        }
        
        if (!empty($struct['offset'])) {
            $offset = abs(intval($struct['offset']));
            $input['page'] = ceil($offset / $pageSize);
        }
        
        $comments = $this->singletonWidget('Widget_Comments_Admin', 'pageSize=' . $pageSize, $input, false);
        $commentsStruct = array();
        
        while ($comments->next()) {
            $commentsStruct[] = array(
                'date_created_gmt'		=> new IXR_Date($this->options->timezone + $comments->created),
                'user_id'				=> $comments->authorId,
                'comment_id'			=> $comments->coid,
                'parent'				=> $comments->parent,
                'status'				=> $this->typechoToWordpressStatus($comments->status, 'comment'),
                'content'				=> $comments->text,
                'link'					=> $comments->permalink,
                'post_id'				=> $comments->cid,
                'post_title'			=> $comments->title,
                'author'				=> $comments->author,
                'author_url'			=> $comments->url,
                'author_email'			=> $comments->mail,
                'author_ip'				=> $comments->ip,
                'type'					=> $comments->type
            );
        }
        
        return $commentsStruct;
    }
    
    /**
     * 获取评论
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param integer $commentId
     * @return boolean
     */
    public function wpDeleteComment($blogId, $userName, $password, $commentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        $commentId = abs(intval($commentId));
        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', $commentId);
        
        if (!$commentWidget->commentIsWriteable($where)) {
            return new IXR_Error(403, _t('无法编辑此评论'));
        }

        return intval($this->singletonWidget('Widget_Abstract_Comments')->delete($where)) > 0;
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
     * @return boolean
     */
    public function wpEditComment($blogId, $userName, $password, $commentId, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        $commentId = abs(intval($commentId));
        $commentWidget = $this->singletonWidget('Widget_Abstract_Comments');
        $where = $this->db->sql()->where('coid = ?', $commentId);
        
        if (!$commentWidget->commentIsWriteable($where)) {
            return new IXR_Error(403, _t('无法编辑此评论'));
        }
        
        $input = array();
        
        if (isset($struct['date_created_gmt'])) {
            $input['created'] = $struct['date_created_gmt']->getTimestamp() - $this->options->timezone + $this->options->serverTimezone;
        }
        
        if (isset($struct['status'])) {
            $input['status'] = $this->wordpressToTypechoStatus($struct['status'], 'comment');
        }
        
        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }
        
        if (isset($struct['author'])) {
            $input['author'] = $struct['author'];
        }
        
        if (isset($struct['author_url'])) {
            $input['url'] = $struct['author_url'];
        }
        
        if (isset($struct['author_email'])) {
            $input['mail'] = $struct['author_email'];
        }
        
        $result = $commentWidget->update((array) $input, $where);
        
        if (!$result) {
            return new IXR_Error(404, _t('评论不存在'));
        }
        
        return true;
    }
    
    /**
     * 更新评论
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $path
     * @param array $struct
     * @return int
     */
    public function wpNewComment($blogId, $userName, $password, $path, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
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
        $input['type']  = 'comment';
        
        if (isset($struct['comment_author'])) {
            $input['author'] = $struct['author'];
        }
        
        if (isset($struct['comment_author_email'])) {
            $input['mail'] = $struct['author_email'];
        }
        
        if (isset($struct['comment_author_url'])) {
            $input['url'] = $struct['author_url'];
        }
        
        if (isset($struct['comment_parent'])) {
            $input['parent'] = $struct['comment_parent'];
        }
        
        if (isset($struct['content'])) {
            $input['text'] = $struct['content'];
        }
        
        try {
            $commentWidget =  $this->singletonWidget('Widget_Feedback', 'checkReferer=false', $input, false);
            $commentWidget->action();
            return intval($commentWidget->coid);
        } catch (Typecho_Exception $e) {
            return new IXR_Error(500, $e->getMessage());
        }
        
        return new IXR_Error(403, _t('无法添加评论'));
    }



    /**
     * 获取媒体文件
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param struct $struct
     * @return boolean
     */
    public function wpGetMediaLibrary($blogId, $userName, $password, $struct)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        

        $input = array();

        if (!empty($struct['parent_id'])) {
            $input['parent'] = $struct['parent_id'];
        }

        if (!empty($struct['mime_type'])) {
            $input['mime'] = $struct['mime_type'];
        }
        
        $pageSize = 10;
        if (!empty($struct['number'])) {
            $pageSize = abs(intval($struct['number']));
        }
        
        if (!empty($struct['offset'])) {
            $input['page'] = abs(intval($struct['offset'])) + 1;
        }
        
        $attachments = $this->singletonWidget('Widget_Contents_Attachment_Admin', 'pageSize=' . $pageSize, $input, false);
        $attachmentsStruct = array();
        
        while ($attachments->next()) {
            $attachmentsStruct[] = array(
                'attachment_id'         => $attachments->cid,
                'date_created_gmt'      => new IXR_Date($this->options->timezone + $attachments->created),
                'parent'                => $attachments->parent,
                'link'                  => $attachments->attachment->url,
                'title'                 => $attachments->title,
                'caption'               => $attachments->slug,
                'description'           => $attachments->attachment->description,
                'metadata'              => array(
                    'file'  =>  $attachments->attachment->path,
                    'size'  =>  $attachments->attachment->size,
                ),
                'thumbnail'             => $attachments->attachment->url,

            );
        }
        return $attachmentsStruct;
    }

    /**
     * 获取媒体文件
     * 
     * @access public
     * @param integer $blogId
     * @param string $userName
     * @param string $password
     * @param int $attachmentId
     * @return boolean
     */
    public function wpGetMediaItem($blogId, $userName, $password, $attachmentId)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }
        
        
        $attachment = $this->singletonWidget('Widget_Contents_Attachment_Edit', NULL, "cid={$attachmentId}");
        $struct = array(
            'attachment_id'         => $attachment->cid,
            'date_created_gmt'      => new IXR_Date($this->options->timezone + $attachment->created),
            'parent'                => $attachment->parent,
            'link'                  => $attachment->attachment->url,
            'title'                 => $attachment->title,
            'caption'               => $attachment->slug,
            'description'           => $attachment->attachment->description,
            'metadata'              => array(
                'file'  =>  $attachment->attachment->path,
                'size'  =>  $attachment->attachment->size,
            ),
            'thumbnail'             => $attachment->attachment->url,

        );
        return $struct;
    }



    /**
     * MetaWeblog API
     * about MetaWeblog API, you can see http://www.xmlrpc.com/metaWeblogApi
     *
     * @param int $blogId
     * @param string $userName
     * @param string $password
     * @param mixed $content
     * @param bool $publish
     * @access public
     * @return int
     */
    public function mwNewPost($blogId, $userName, $password, $content, $publish)
    {
        /** 检查权限*/
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        /** 取得content内容 */
        $input = array();
        $type = isset($content['post_type']) && 'page' == $content['post_type'] ? 'page' : 'post';
        
        $input['title'] = trim($content['title']) == NULL ? _t('未命名文档') : $content['title'];

        if (isset($content['slug'])) {
            $input['slug'] = $content['slug'];
        } else if (isset($content['wp_slug'])) {
            //fix issue 338, wlw只发送这个
            $input['slug'] = $content['wp_slug'];
        }

        $input['text'] = !empty($content['mt_text_more']) ? $content['description'] 
            . "\n<!--more-->\n" . $content['mt_text_more'] : $content['description'];
        $input['text'] = $this->pluginHandle()->textFilter($input['text'], $this); 
        
        $input['password'] = isset($content["wp_password"]) ? $content["wp_password"] : NULL;
        $input['order'] = isset($content["wp_page_order"]) ? $content["wp_page_order"] : NULL;

        $input['tags'] = isset($content['mt_keywords']) ? $content['mt_keywords'] : NULL;
        $input['category'] = array();

        if (isset($content['postId'])) {
            $input['cid'] = $content['postId'];
        }
        
        if ('page' == $type && isset($content['wp_page_template'])) {
            $input['template'] = $content['wp_page_template'];
        }

        if (isset($content['dateCreated'])) {
            /** 解决客户端与服务器端时间偏移 */
            $input['created'] = $content['dateCreated']->getTimestamp() - $this->options->timezone + $this->options->serverTimezone;
        }

        if (!empty($content['categories']) && is_array($content['categories'])) {
            foreach ($content['categories'] as $category) {
                if (!$this->db->fetchRow($this->db->select('mid')
                ->from('table.metas')->where('type = ? AND name = ?', 'category', $category))) {
                    $result = $this->wpNewCategory($blogId, $userName, $password, array('name' => $category));
                    if (true !== $result) {
                        return $result;
                    }
                }

                $input['category'][] = $this->db->fetchObject($this->db->select('mid')
                ->from('table.metas')->where('type = ? AND name = ?', 'category', $category)
                ->limit(1))->mid;
            }
        }

        $input['allowComment'] = (isset($content['mt_allow_comments']) && (1 == $content['mt_allow_comments']
        || 'open' == $content['mt_allow_comments'])) ? 1 : ((isset($content['mt_allow_comments']) && (0 == $content['mt_allow_comments']
        || 'closed' == $content['mt_allow_comments'])) ? 0 : $this->options->defaultAllowComment);

        $input['allowPing'] = (isset($content['mt_allow_pings']) && (1 == $content['mt_allow_pings']
        || 'open' == $content['mt_allow_pings'])) ? 1 : ((isset($content['mt_allow_pings']) && (0 == $content['mt_allow_pings']
        || 'closed' == $content['mt_allow_pings'])) ? 0 : $this->options->defaultAllowPing);

        $input['allowFeed'] = $this->options->defaultAllowFeed;
        $input['do'] = $publish ? 'publish' : 'save';
        $input['markdown'] = $this->options->xmlrpcMarkdown;
        
        /** 调整状态 */
        if (isset($content["{$type}_status"])) {
            $status = $this->wordpressToTypechoStatus($content["{$type}_status"], $type);
            $input['visibility'] = isset($content["visibility"]) ? $content["visibility"] : $status;
            if ('publish' == $status || 'waiting' == $status || 'private' == $status) {
                $input['do'] = 'publish';
                
                if ('private' == $status) {
                    $input['private'] = 1;
                }
            } else {
                $input['do'] = 'save';
            }
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
        
            return $this->singletonWidget('Widget_Notice')->getHighlightId();
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }
    }

    /**
     * 编辑post
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @param struct $content
     * @param bool $publish
     * @access public
     * @return int
     */
    public function mwEditPost($postId, $userName, $password, $content, $publish = true)
    {
        $content['postId'] = $postId;
        return $this->mwNewPost(1, $userName, $password, $content, $publish);
    }

    /**
     * 获取指定id的post
     *
     * @param int $postId
     * @param string $userName
     * @param string $password
     * @access public
     * @return void
     */
    public function mwGetPost($postId, $userName, $password)
    {
        if (!$this->checkAccess($userName, $password)) {
            return $this->error;
        }

        try {
            $post = $this->singletonWidget('Widget_Contents_Post_Edit', NULL, "cid={$postId}");
        } catch (Typecho_Widget_Exception $e) {
            return new IXR_Error($e->getCode(), $e->getMessage());
        }

        /** 对文章内容做截取处理，以获得description和text_more*/
        list($excerpt, $more) = $this->getPostExtended($post);
        /** 只需要分类的name*/
        $categories = Typecho_Common::arrayFlatten($post->categories, 'name');
        $tags = Typecho_Common::arrayFlatten($post->tags, 'name');

        $postStruct = array(
                'dateCreated'   => new IXR_Date($this->options->timezone + $post->created),
                'userid'        => $post->authorId,
                'postid'       => $post->cid,
                'description'   => $excerpt,
                'title'         => $post->title,
                'link'          => $post->permalink,
                'permaLink'     => $post->permalink,
                'categories'    => $categories,
                'mt_excerpt'    => $post->description,
                'mt_text_more'  => $more,
                'mt_allow_comments' => intval($post->allowComment),
                'mt_allow_pings'    => intval($post->allowPing),
                'mt_keywords'	=> implode(', ', $tags),
                'wp_slug'       => $post->slug,
                'wp_password'   => $post-
