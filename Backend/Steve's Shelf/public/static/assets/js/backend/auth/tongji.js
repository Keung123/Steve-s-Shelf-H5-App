define(['jquery', 'bootstrap', 'backend', 'addtabs', 'table', 'echarts', 'echarts-theme'], function ($, undefined, Backend, Datatable, Table, Echarts) {
    var Controller = {
        index: function () {
            // 基于准备好的dom，初始化echarts实例
            var myChart1 = Echarts.init(document.getElementById('bingzhuangtu'));
            var myChart2 = Echarts.init(document.getElementById('bolangtu'));
            // option = {
            //     title : {
            //         text: '财务统计',
            //         subtext: '',
            //         x:'left'
            //     },
            //     tooltip : {
            //         trigger: 'item',
            //         formatter: "{a} <br/>{b} : {c} ({d}%)"
            //     },
            //     legend: {
            //         orient : 'vertical',
            //         x : 'right',
            //         y : 'center',
            //         data:['商品成本','平台获利','返利利润','抵扣金额']
            //     },
            //     toolbox: {
            //         show : false,
            //         feature : {
            //             mark : {show: true},
            //             dataView : {show: true, readOnly: false},
            //             magicType : {
            //                 show: true,
            //                 type: ['pie', 'funnel'],
            //                 option: {
            //                     funnel: {
            //                         x: '25%',
            //                         width: '50%',
            //                         funnelAlign: 'left',
            //                         max: 1548
            //                     }
            //                 }
            //             },
            //             restore : {show: true},
            //             saveAsImage : {show: true}
            //         }
            //     },
            //     calculable : true,
            //     series : [
            //         {
            //             name:'访问来源',
            //             type:'pie',
            //             radius : '55%',
            //             center: ['50%', '60%'],
            //             data:[
            //                 {value:chart_data.cost, name:'商品成本'},
            //                 {value:chart_data.profit, name:'平台获利'},
            //                 {value:chart_data.commission, name:'返利利润'},
            //                 {value:chart_data.discount, name:'抵扣金额'}
            //             ]
            //         }
            //     ]
            // };

            option = {
                title : {
                    text: '财务统计',
                    subtext: '',
                    x:'left'
                },
                tooltip : {
                    trigger: 'axis',
                    // formatter: "{a} <br/>{b} : {c} (元)"
                },
                legend: {
                    orient : 'vertical',
                    x : 'right',
                    y : 'center',
                    data:['商品成本','平台获利','返利利润','抵扣金额']
                },
                toolbox: {

                },
                xAxis: {
                    type: 'category',
                    boundaryGap: true,
                    data: data_xx,
                },
                yAxis: {
                    type: 'value',
                    axisLabel : {
                        formatter: '{value} 元'
                    }
                },
                series: [
                    {
                        name:'商品成本',
                        type:'line',
                        stack: '总量',
                        data:cost,
                        
                    },
                    {
                        name:'平台获利',
                        type:'line',
                        stack: '总量',
                        data:profit
                    },
                    {
                        name:'返利利润',
                        type:'line',
                        stack: '总量',
                        data:commissions
                    },
                    {
                        name:'抵扣金额',
                        type:'line',
                        stack: '总量',
                        data:discounts
                    }
                ]
            };

            myChart1.setOption(option);
            option1 = {
                title: {
                    text: "销售额",
                    x: "left"
                },
                tooltip: {
                    trigger: "axis",
                    // formatter: "{a} <br/>{b} : {c}元"
                },
                legend: {
                    x: 'right',
                    y: 'center',
                    data: ["销售额"]
                },
                xAxis: [
                    {
                        type: "category",
                       // splitLine: {show: false},
                        data: data_x,
                    }
                ],
                yAxis: {
                    type: 'value',
                    axisLabel : {
                        formatter: '{value} 元'
                    }
                },
                toolbox: {
                    show: false,
                    feature: {
                        mark: {
                            show: true
                        },
                        dataView: {
                            show: true,
                            readOnly: true
                        },
                        restore: {
                            show: true
                        },
                        saveAsImage: {
                            show: true
                        }
                    }
                },
                calculable: true,
                series: [
                    {
                        name: "销售额",
                        type: "line",
                        data: y_val
                    }
                ]
            };
            myChart2.setOption(option1);
        }
    };

    return Controller;
});