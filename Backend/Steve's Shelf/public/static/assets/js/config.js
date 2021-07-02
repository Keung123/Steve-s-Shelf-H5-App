            var require = {
                "config": {
                    "site": {
                        "name": "FastAdmin",
                        "cdnurl": root,
                        "version": debug?(new Date()).getTime():version,
                        "timezone": "Asia/Shanghai",
                        "languages": {
                            "backend": "zh-cn",
                            "frontend": "zh-cn"
                        }
                    },
                    "upload": {
                        "cdnurl": '',
                        "uploadurl": '/admin/upload/index',
                        "bucket": "yourbucketname",
                        "maxsize": "300mb",
                        "mimetype": "*",
                        "multipart": {
                            "policy": "eyJidWNrZXQiOiJ5b3VyYnVja2V0bmFtZSIsInNhdmUta2V5IjoiXC91cGxvYWRzXC97eWVhcn17bW9ufXtkYXl9XC97ZmlsZW1kNX17LnN1ZmZpeH0iLCJleHBpcmF0aW9uIjoxNTAwNTI2NTczLCJub3RpZnktdXJsIjoiaHR0cDpcL1wvd3d3LnlvdXJzaXRlLmNvbVwvdXB5dW5cL25vdGlmeSJ9",
                            "signature": "043eaf09c0319b1a9a11d06511bfdc4e",
                            "bucket": "yourbucketname",
                            "save-key": "/uploads/{year}{mon}{day}/{filemd5}{.suffix}",
                            "expiration": 1500526573,
                            "notify-url": "http://www.yoursite.com/upyun/notify"
                        },
                        "multiple": false
                    },
                    "modulename": "admin",
                    "controllername": typeof controllername!='undefined'&&controllername?controllername:"index",
                    "actionname": typeof actionname!='undefined'&&actionname?actionname:"index",
                    "jsname": typeof jsname!='undefined'&&jsname?jsname:"backend/index",
                    "moduleurl": "/admin",
                    "language": "zh-cn",
                    "referer": null
                }
            };