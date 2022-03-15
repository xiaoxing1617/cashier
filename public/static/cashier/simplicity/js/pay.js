//insert
function preselections(e){
    e.preventDefault();
    var target = e.target;
    var value = target.getAttribute('data-number');
    var dot = valueCur.match(/\.\d{2,}$/);
    if(!value){
        return;
    }
    valueCur = valueCur === '0' ? value : value;
    if(!!valueCur && value !== 'delete' && value !== 'dot') {
        var re = /^\d{1,9}(\.\d{0,2})?$/;
        var limitLen = re.test(valueCur);
        if (!limitLen) {
            valueCur = valueCur.slice(0,valueCur.length-1);
            return;
        }
    }
    format();
}
function keypress(e){
    e.preventDefault();
    var target = e.target;
    var value = target.getAttribute('data-value');
    var dot = valueCur.match(/\.\d{2,}$/);
    if(!value || (value !== 'delete' && dot)){
        return;
    }

    switch(value){
        case '0' :
            valueCur = valueCur === '0' ? valueCur : valueCur + value;
            break;
        case 'dot' : 
            valueCur = valueCur === '' ? valueCur : valueCur.indexOf('.') > -1 ? valueCur : valueCur + '.'; 
            break;
        case 'delete' : 
            valueCur = valueCur.slice(0,valueCur.length-1);
            break;
        default : 
            valueCur = valueCur === '0' ? value : valueCur + value;
    }

    if(!!valueCur && value !== 'delete' && value !== 'dot') {
        var re = /^\d{1,9}(\.\d{0,2})?$/;
        var limitLen = re.test(valueCur);
        if (!limitLen) {
            valueCur = valueCur.slice(0,valueCur.length-1);
            return;
        }
    }
    format();
}

//format
function format(){
    var arr = valueCur.split('.');
    var right = arr.length === 2 ? '.'+arr[1] : '';
    var num = arr[0];
    var left = '';
    while(num.length > 3){
        left = ',' + num.slice(-3) + left;
        num = num.slice(0,num.length - 3);
    }
    left = num + left;
    valueFormat = left+right;
    valueFinal = valueCur === '' ? 0 : parseFloat(valueCur);
    check();
}

//check
function check(){
    amount.innerHTML = valueFormat;
    if(valueFormat.length > 0){
        clearBtn.classList.remove('none');
    }else{
        clearBtn.classList.add('none');
    }
    if(valueFinal === 0 || valueCur.match(/\.$/)){
        payBtn.classList.add('disable');
    }else{
        payBtn.classList.remove('disable');
    }
}

//clear
function clearFun(){
    valueCur = '';
    valueFormat = '';
    valueFinal = 0;
    amount.innerHTML = '';
    clearBtn.classList.add('none');
    payBtn.classList.add('disable');
}

//submit
function submitFun(){	
    if(!submitAble || payBtn.classList.contains('disable')){
        return;
    }
    var txAmount = $("#txAmount").val();
    if (!!txAmount && txAmount > 0) {
        valueFinal = txAmount;
    }
    if(valueFinal == 0){	
        tips.show('请输入金额！');
        return;
    }

    var amount = valueFinal;

    submitAble = false;
    loading.show();
    console.log(amount);

    $('#pay_type').val();  //支付方式
    $('#pay_money').val(amount);  //支付金额

    $("#pay_form").submit();
}

var preselection = getId('preselection');
var keyboard = getId('keyboard');
var clearBtn = getId('clearBtn');
var payBtn = getId('payBtn');
var valueCur = '';
var valueFormat = '';
var submitAble = true;
var valueFinal = 0;

new Hammer(preselection).on('tap',preselections);
new Hammer(keyboard).on('tap',keypress);
new Hammer(payBtn).on('tap',submitFun);
new Hammer(clearBtn).on('tap',clearFun);

function getId(value){
    return document.getElementById(value);
}

//loading
function Loading(){
    var obj = document.createElement('div');
    var box = document.createElement('div');
    var img = document.createElement('div');
    var txt = document.createElement('p');

    obj.className = 'circle-box none';
    box.className = 'circle_animate';
    img.className = 'circle';
    box.appendChild(img);
    box.appendChild(txt);
    obj.appendChild(box);
    if(script){
        script.parentNode.insertBefore(obj,script);
    }else{
        document.body.appendChild(obj);
    }

    this.show = function(value){
        txt.innerHTML = value || '发起支付';
        obj.classList.remove('none');
    };

    this.hide = function(){
        obj.classList.add('none');
        txt.innerHTML = '';
    };
}

//tips
function Tips(){
    var obj = document.createElement('div');
    var box = document.createElement('div');
    var con = document.createElement('div');
    var txt = document.createElement('div');
    var p = document.createElement('p');
    var btnBox = document.createElement('p');
    var btn = document.createElement('span');

    obj.className = 'pop_wrapper none';
    box.className = 'pop_outer';
    con.className = 'pop_cont';
    txt.className = 'pop_tip';
    p.className = 'border b_top';
    btnBox.className = 'pop_wbtn';
    btn.className='pop_btn'

    btn.innerHTML = '我知道了';

    p.appendChild(btn);
    con.appendChild(txt);
    con.appendChild(p);
    box.appendChild(con);
    obj.appendChild(box);
    if(script){
        script.parentNode.insertBefore(obj,script);
    }else{
        document.body.appendChild(obj);
    }

    function hideFun(){
        obj.classList.add('none');
    }

    this.show = function(value,callback){
        var fun = callback || hideFun;
        txt.innerHTML = value || ' ';
        p.onclick = callback || hideFun;
        obj.classList.remove('none');
    };

    this.hide = hideFun;
}

document.body.addEventListener('touchstart',function(){},false);
var script = document.body.getElementsByTagName('script')[0];
var loading = new Loading();
