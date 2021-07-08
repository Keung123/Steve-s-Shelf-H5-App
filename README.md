# Steve-s-Shelf-H5-App

#### 服务器搭建

1. ##### [宝塔服务器运维面板](https://www.bt.cn/bbs/thread-19376-1-1.html)

   ```bash
   # 安装需要纯净环境
   # 端口要求：
   #	20 、21、 39000-40000（linux 系统 ）、3000-4000（windows系统）
   #	、22 （SSH）、80、443（网站及SSL）、3306 （数据库远程连接）、888 （phpmyadmin）
   
   yum install -y wget && wget -O install.sh http://download.bt.cn/install/install_6.0.sh && sh install.sh
   
   # 安装后从安全入口进入面板安装环境
   /etc/init.d/bt default
   # 版本要求：
   #	Nginx 1.18
   #	PHP 7.1
   
   ```

   

2. ##### 导入应用和数据库

   ```bash
   # 上传应用文件
   # 配置面板入口
   ```

   

3. ##### 通过 composer 配置插件

   ```bash
   # 更新 composer
   composer selfupdate
   # 允许 putenv() 和 proc_open(),在输出的配置文件路径里修改
   php -i | grep "Loaded Configuration File"
   
   # 安装插件
   composer install
   # 更新插件
   composer update --no-plugins
   ```

   

4. ##### 更新 api 和 backend 地址

   ```bash
   # SQL文件
   # 前端的commmen.js
   ```

