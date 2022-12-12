/**
 * 生成普通样式二维码
 * @param {HTMLElement} element
 * @param {Number} qrWidth 二维码宽度
 * @param {Number} qrHeight 二维码高度
 * @param {String} tinyurl 二维码地址、内容
 * @param {String} foreground 前景色
 * @param {String} background 背景色
 * @param {String} imgUrl 背景地址
 * @param {Number} imgWidth 图片宽度
 * @param {Number} imgHeight 图片高度
 * @param {Array} texts 文本组[{font:文本字体,color:文本颜色,txt:文本内容,left:文本左侧距离,top:文本顶部距离}]
 * @param {Number} qrLeft 二维码左侧距离
 * @param {Number} qrTop 二维码顶部距离
 */
function makeDiyBg(element, qrWidth, qrHeight, tinyurl, foreground, background, imgUrl, imgWidth, imgHeight, texts, qrLeft, qrTop, download = false, other_data = null, circular_portrait = null) {
    $(element).html("");
    $(element).qrcode({
        render: "canvas",
        width: qrWidth,
        height: qrHeight,
        text: tinyurl,
        foreground: foreground,
        background: background
    });
    var canvas = document.getElementById('canvas');
    canvas.width = imgWidth;
    canvas.height = imgHeight;
    var ctx = canvas.getContext("2d");


    var img = new Image();
    img.crossOrigin = "Anonymous"
    img.src = imgUrl;
    var base64 = "";
    img.onload = function () {
        // 生成背景图
        var bg = ctx.createPattern(img, "no-repeat");

        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, imgWidth, imgHeight);
        // 文本
        if(texts){
            for( key in texts){
                const item = texts[key];
                ctx.textAlign = item.align?item.align:'center';
                ctx.font = item.font;
                ctx.fillStyle = item.color;
                if (!item.left) {
                    item.left = imgWidth / 2;
                }
                ctx.fillText(item.txt, item.left, item.top);
            }
        }
        // 在canvas上生成二维码
        var canvasOld = document.getElementsByTagName('canvas')[0];
        ctx.drawImage(canvasOld, qrLeft, qrTop);

        //QQ头像（圆形）
        if (circular_portrait != null) {
            var img1 = new Image();
            img1.src = "http://q1.qlogo.cn/g?b=qq&nk=" + other_data.qq + "&s=640";
            img1.src = "/favicon.ico";
            img1.left = circular_portrait.left;
            img1.top = circular_portrait.top;
            img1.width = circular_portrait.width;
            img1.height = circular_portrait.height;
            img1.onload = function () {
                circleImg(ctx, img1, img1.left, img1.top, img1.width / 2);
            }
            delete img1;
        }
    }
    return base64;
}

// r: 半径
function circleImg(ctx, img, x, y, r) {
    var d = 2 * r;
    var cx = x + r;
    var cy = y + r;
    ctx.arc(cx, cy, r, 0, 2 * Math.PI);
    ctx.clip();
    ctx.drawImage(img, x, y, d, d);
}


function draw(item,ctx){
    ctx.beginPath();
    ctx.moveTo(item.locations[0][0],item.locations[0][1]);
    for(let i = 0;i<item.locations.length;i  ){
        let x = item.locations[i][0];
        let y = item.locations[i][1];
        ctx.lineTo(x,y);
    }
    ctx.closePath();

    ctx.fillStyle = item.color;
    ctx.fill();

    ctx.strokeStyle = "#000";
    ctx.lineWidth = 2;
    ctx.stroke();
}
var imgId = document.getElementById("canvas");
document.getElementById("save").onclick = function (){
    downLoad(saveAsPNG(imgId));
}

// 保存成png格式的图片
function saveAsPNG(canvas) {
    return canvas.toDataURL("image/png");
}

// 保存成jpg格式的图片
function saveAsJPG(canvas) {
    return canvas.toDataURL("image/jpeg");
}

// 保存成bmp格式的图片  目前浏览器支持性不好
function saveAsBMP(canvas) {
    return canvas.toDataURL("image/bmp");
}

/**
 * @author web得胜
 * @param {String} url 需要下载的文件地址
 * */
function downLoad(url){
    var oA = document.createElement("a");
    oA.download = Date.parse(new Date());// 设置下载的文件名，默认是'下载'
    oA.href = url;
    document.body.appendChild(oA);
    oA.click();
    oA.remove(); // 下载之后把创建的元素删除
}
