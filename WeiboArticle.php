<?php


	namespace QL\Ext;

	use QL\Contracts\PluginContract;
	use QL\QueryList;

	/**
	 *  微博文章搜索插件
	 * Created by Malcolm.
	 * Date: 2021/5/19  09:40
	 */
	class WeiboArticle implements PluginContract
	{

		const API = 'https://s.weibo.com/article';
		const RULES = [
			'title' => [ 'h3' , 'text' ] ,
			'link'  => [ 'h3>a' , 'href' ]
		];
		const RANGE = '.card-wrap';
		protected $ql;
		protected $keyword;
		protected $pageNumber = 10;
		protected $httpOpt = [
			'headers' => [
				'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36' ,
				'Accept-Encoding' => 'gzip, deflate, br' ,
				'Referer'         => 'https://s.weibo.com/?Refer=search_need_login' ,
				'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9' ,
				'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8'
			]
		];

		public function __construct ( QueryList $ql , $pageNumber ) {
			$this->ql = $ql->rules( self::RULES )
			               ->range( self::RANGE );
			$this->pageNumber = $pageNumber;
		}

		public static function install ( QueryList $queryList , ...$opt ) {
			$name = $opt[0] ?? 'weiboArticle';
			$queryList->bind( $name , function ( $pageNumber = 10 )
			{
				return new WeiboArticle( $this , $pageNumber );
			} );
		}

		public function setHttpOpt ( array $httpOpt = [] ) {
			$this->httpOpt = $httpOpt;
			return $this;
		}

		public function search ( $keyword ) {
			$this->keyword = $keyword;
			return $this;
		}

		public function page ( $page = 1 , $realURL = false ) {
			return $this->query( $page )
			            ->query()
			            ->getData( function ( $item ) use ( $realURL )
			            {
				            if ( isset( $item['title'] ) && $item['title'] ) {
					            $encode = mb_detect_encoding( $item['title'] , array( "ASCII" , 'UTF-8' , "GB2312" , "GBK" , 'BIG5' ) );
					            $item['title'] = iconv( $encode , 'UTF-8' , $item['title'] );
				            }
				            $realURL && $item['link'] = $this->getRealURL( $item['link'] );
				            return $item;
			            } );
		}

		protected function query ( $page = 1 ) {
			$this->ql->get( self::API , [
				'q'         => $this->keyword ,
				'page'      => $this->pageNumber ,
				'limitType' => 'article' ,
			] , $this->httpOpt );
			return $this->ql;
		}

		/**
		 * 得到百度跳转的真正地址
		 * @param $url
		 * @return mixed
		 */
		protected function getRealURL ( $url ) {
			if ( empty( $url ) ) {
				return $url;
			}
			$header = get_headers( $url , 1 );
			if ( strpos( $header[0] , '301' ) || strpos( $header[0] , '302' ) ) {
				if ( is_array( $header['Location'] ) ) {
					//return $header['Location'][count($header['Location'])-1];
					return $header['Location'][0];
				} else {
					return $header['Location'];
				}
			} else {
				return $url;
			}
		}

		public function getCountPage () {
			$count = $this->getCount();
			$countPage = ceil( $count / $this->pageNumber );
			return $countPage;
		}

		public function getCount () {
			$count = 0;
			$text = $this->query( 1 )
			             ->find( '.nums' )
			             ->text();
			if ( preg_match( '/[\d,]+/' , $text , $arr ) ) {
				$count = str_replace( ',' , '' , $arr[0] );
			}
			return (int) $count;
		}

	}