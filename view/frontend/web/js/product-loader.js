define(['jquery'], function($){
    'use strict';
    function parseParams(url){
      const params = url.split('?')
      if(params.length > 1){
        return JSON.parse('{"' + decodeURI(params[1].replace(/&/g, "\",\"").replace(/=/g,"\":\"")) + '"}')
      }
      return {}
    }
    return function(config, element){
      let page = 0;
      const loadProduct = function(url, options = {},cleanFirst = false){

        const resultElWrapper = $(element)
        if(cleanFirst){
          resultElWrapper.empty()
        }
        const loadTrigger = $('#product-load-trigger')
        loadTrigger.attr('disabled','disabled')
        loadTrigger.text('Loading....')
        resultElWrapper.addClass('loading')
        const data = Object.assign({
          '__a':1
        },options)
        return new Promise((resolve,reject)=>{
          $.ajax({
            url,
            type:'get',
            data
          }).then(result =>{
            $('.filter-current').remove()
            $('.filter-content').prepend(result.state)
            loadTrigger.removeAttr('disabled')
            loadTrigger.text(loadTrigger.data('text'))
            resultElWrapper.append(result.html)
            resultElWrapper.removeClass('loading')
            const totitem = result.total
            const page = result.page
            const perpage = result.limit
            const prodToShown = perpage * page

            if(prodToShown >= totitem){
              $('#product-load-trigger').hide()
            }else{
              $('#product-load-trigger').show()
            }
            resolve(result)
          })
        })
      }

      var baseUrl = window.location.href
      var curFilter = parseParams(baseUrl)
      $(document).ready(function(){
         loadProduct(baseUrl).then(function(result){
           page = result.page
         })
      })
      $('#product-load-trigger').click(function(e){
         e.preventDefault()
         loadProduct(baseUrl,Object.assign(curFilter,{
           'p':page+1
         })).then(function(result){
           page = result.page
         })
      })
      $('.filter-options-content a').click(function(e){
         e.preventDefault()
         curFilter = Object.assign({},
           curFilter,
           parseParams($(this).attr('href')),
           {'p':1}
         );
         loadProduct($(this).attr('href'),curFilter, true).then(function(result){
           page = result.page
         })
      })
      $('.filter-content').on('click','.filter-current a', function(e){
        e.preventDefault()
        curFilter = Object.assign(parseParams($(this).attr('href')),{
          'p':1
        })
        loadProduct($(this).attr('href'),curFilter, true).then(function(result){
          page = result.page
        })
      })
    }
});
