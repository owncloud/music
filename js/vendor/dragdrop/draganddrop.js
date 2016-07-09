/**
 * Created with IntelliJ IDEA.
 * User: Ganaraj.Pr
 * Date: 11/10/13
 * Time: 11:27
 * To change this template use File | Settings | File Templates.
 */
 
(function(){

function isDnDsSupported(){
    return 'draggable' in document.createElement("span");
}    

if(!isDnDsSupported()){
    return;
}
 
if (window.jQuery && (-1 == window.jQuery.event.props.indexOf("dataTransfer"))) {
    window.jQuery.event.props.push("dataTransfer");
}

angular.module("ngDragDrop",[])
    .directive("uiDraggable", [
        '$parse',
        '$rootScope',
        function ($parse, $rootScope) {
            return function (scope, element, attrs) {
                var dragData = "",
                    isDragHandleUsed = false,
                    dragHandleClass,
                    dragHandles,
                    dragTarget;

                element.attr("draggable", false);

                attrs.$observe("uiDraggable", function (newValue) {
                    if(newValue){
                        element.attr("draggable", newValue);
                    }
                    else{
                        element.removeAttr("draggable");
                    }
                    
                });

                if (attrs.drag) {
                    scope.$watch(attrs.drag, function (newValue) {
                        dragData = newValue || "";
                    });
                }

                if (angular.isString(attrs.dragHandleClass)) {
                    isDragHandleUsed = true;
                    dragHandleClass = attrs.dragHandleClass.trim() || "drag-handle";
                    dragHandles = element.find('.' + dragHandleClass).toArray();

                    element.bind("mousedown", function (e) {
                        dragTarget = e.target;
                    });
                }

                element.bind("dragstart", function (e) {
                    var isDragAllowed = !isDragHandleUsed || -1 != dragHandles.indexOf(dragTarget);

                    if (isDragAllowed) {
                        var sendChannel = attrs.dragChannel || "defaultchannel";
                        var sendData = angular.toJson({ data: dragData, channel: sendChannel });
                        var dragImage = attrs.dragImage || null;
                        if (dragImage) {
                            var dragImageFn = $parse(attrs.dragImage);
                            scope.$apply(function() {
                                var dragImageParameters = dragImageFn(scope, {$event: e});
                                if (dragImageParameters && dragImageParameters.image) {
                                    var xOffset = dragImageParameters.xOffset || 0,
                                        yOffset = dragImageParameters.yOffset || 0;
                                    e.dataTransfer.setDragImage(dragImageParameters.image, xOffset, yOffset);
                                }
                            });
                        }

                        e.dataTransfer.setData("Text", sendData);
                        e.dataTransfer.effectAllowed = "copyMove";
                        $rootScope.$broadcast("ANGULAR_DRAG_START", sendChannel);
                    }
                    else {
                        e.preventDefault();
                    }
                });

                element.bind("dragend", function (e) {
                    var sendChannel = attrs.dragChannel || "defaultchannel";
                    $rootScope.$broadcast("ANGULAR_DRAG_END", sendChannel);
                    if (e.dataTransfer && e.dataTransfer.dropEffect !== "none") {
                        if (attrs.onDropSuccess) {
                            var fn = $parse(attrs.onDropSuccess);
                            scope.$apply(function () {
                                fn(scope, {$event: e});
                            });
                        }
                    }
                });


            
        };
        }
    ])
    .directive("uiOnDrop", [
        '$parse',
        '$rootScope',
        function ($parse, $rootScope) {
            return function (scope, element, attr) {
                var dragging = 0; //Ref. http://stackoverflow.com/a/10906204
                var dropChannel = attr.dropChannel || "defaultchannel" ;
                var dragChannel = "";
                var dragEnterClass = attr.dragEnterClass || "on-drag-enter";
                var dragHoverClass = attr.dragHoverClass || "on-drag-hover";

                function onDragOver(e) {
                    if (e.preventDefault) {
                        e.preventDefault(); // Necessary. Allows us to drop.
                    }

                    if (e.stopPropagation) {
                        e.stopPropagation();
                    }

                    e.dataTransfer.dropEffect = e.shiftKey ? 'copy' : 'move';
                    return false;
                }

                function onDragLeave(e) {
                  dragging--;
                  if (dragging == 0) {
                    element.removeClass(dragHoverClass);
                    element.removeClass(dragEnterClass);
                  }
                }

                function onDragEnter(e) {
                    dragging++;
                    $rootScope.$broadcast("ANGULAR_HOVER", dropChannel);
                    element.addClass(dragHoverClass);
                }

                function onDrop(e) {
                    if (e.preventDefault) {
                        e.preventDefault(); // Necessary. Allows us to drop.
                    }
                    if (e.stopPropagation) {
                        e.stopPropagation(); // Necessary. Allows us to drop.
                    }

                    var sendData = e.dataTransfer.getData("Text");
                    sendData = angular.fromJson(sendData);

                    var fn = $parse(attr.uiOnDrop);
                    scope.$apply(function () {
                        fn(scope, {$data: sendData.data, $event: e, $channel: sendData.channel});
                    });
                    element.removeClass(dragEnterClass);
                }

                function isDragChannelAccepted(dragChannel, dropChannel) {
                    if (dropChannel === "*") {
                        return true;
                    }

                    var channelMatchPattern = new RegExp("(\\s|[,])+(" + dragChannel + ")(\\s|[,])+", "i");

                    return channelMatchPattern.test("," + dropChannel + ",");
                }

                var deregisterDragStart = $rootScope.$on("ANGULAR_DRAG_START", function (event, channel) {
                    dragChannel = channel;
                    if (isDragChannelAccepted(channel, dropChannel)) {

                        element.bind("dragover", onDragOver);
                        element.bind("dragenter", onDragEnter);
                        element.bind("dragleave", onDragLeave);

                        element.bind("drop", onDrop);
                    }

                });



                var deregisterDragEnd = $rootScope.$on("ANGULAR_DRAG_END", function (e, channel) {
                    dragChannel = "";
                    if (isDragChannelAccepted(channel, dropChannel)) {

                        element.unbind("dragover", onDragOver);
                        element.unbind("dragenter", onDragEnter);
                        element.unbind("dragleave", onDragLeave);

                        element.unbind("drop", onDrop);
                        element.removeClass(dragHoverClass);
                        element.removeClass(dragEnterClass);
                    }
                });


                var deregisterDragHover = $rootScope.$on("ANGULAR_HOVER", function (e, channel) {
                    if (isDragChannelAccepted(channel, dropChannel)) {
                      element.removeClass(dragHoverClass);
                    }
                });
                
                
                scope.$on('$destroy', function () {
                    deregisterDragStart();
                    deregisterDragEnd();
                    deregisterDragHover();
                });


                attr.$observe('dropChannel', function (value) {
                    if (value) {
                        dropChannel = value;
                    }
                });


            };
        }
    ]);
    
    
}());