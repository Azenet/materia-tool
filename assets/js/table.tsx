import * as React from "react";
import * as ReactDOM from "react-dom";
import * as axios from "axios";

import Select from 'react-select';

import {Accordion, Button, Card, Form} from 'react-bootstrap';

import {DndProvider, DragSource, DropTarget} from 'react-dnd';
import Backend from 'react-dnd-html5-backend';

import {Subject, Subscription} from 'rxjs';

import * as marked from 'marked';

const characters: any = {
    c: 'Cloud',
    b: 'Barret',
    t: 'Tifa',
    a: 'Aerith',
    i: 'Inventory'
};

let materiaChoices: any = [];
let materiaMatch: any = [];
const emptyMateriaChoice = {
    label: 'Empty',
    value: 0,
    color: 'black'
};

let loadoutId: any = null;
let completeIndentLevels: any = [];
let materiaCoordinatePreference = Number.parseInt(document.querySelector('meta[name=materia-preference]').getAttribute('content')) || 0;

const refreshRequestObservable = new Subject();

class MateriaCoordinateDisplay extends React.Component<{ character: string, row: number, col: number }, any> {
    render() {
        let value;

        if (materiaCoordinatePreference === 4) {
            if (this.props.row === 0) {
                value = this.props.col < 4 ? this.props.col + 1 : String.fromCharCode(61 + this.props.col);
            } else {
                value = this.props.col + 5;
            }
        } else if (materiaCoordinatePreference === 5) {
            if (this.props.col === 6) {
                value = 'S';
            } else {
                value = (this.props.row * 6) + this.props.col + 1
            }
        } else {
            if (materiaCoordinatePreference % 2 === 0) {
                value = `${String.fromCharCode(65 + this.props.row)}${this.props.col + 1}`;
            } else {
                if (this.props.row === 0) {
                    value = this.props.col + 1;
                } else {
                    value = String.fromCharCode(65 + this.props.col);
                }
            }

            if (materiaCoordinatePreference >= 2) {
                value = this.props.character.toUpperCase() + value;
            }
        }

        return <span>{value}</span>;
    }
}

const collectSource = (connect: any, monitor: any) => {
    return {
        connectDragSource: connect.dragSource(),
        connectDragPreview: connect.dragPreview(),
        isDragging: monitor.isDragging()
    };
}

const collectTarget = (connect: any, monitor: any) => {
    return {
        connectDropTarget: connect.dropTarget(),
        isOver: monitor.isOver(),
        isOverCurrent: monitor.isOver({shallow: true}),
        canDrop: monitor.canDrop(),
        itemType: monitor.getItemType(),
    };
}

const MateriaCell = DropTarget("materia-cells", {
    drop(props: any, monitor: any, component: any) {
        const previousSelected = Object.assign({}, component.decoratedRef.current.state);
        const droppedOn = monitor.getItem();
        const pinned = !!droppedOn.pinned;

        component.decoratedRef.current.setState({
            swapping: true
        }, () => {
            component.decoratedRef.current.onChange(droppedOn.selected).then(() => {
                component.decoratedRef.current.doCheckboxChange(pinned);
            });
        });

        return previousSelected;
    }
}, collectTarget)(DragSource("materia-cells", {
    beginDrag(props: any, monitor: any, component: any) {
        return {selected: component.state.selected, pinned: !!component.state.raw?.pinned};
    },
    endDrag(props: any, monitor: any, component: any) {
        if (monitor.didDrop()) {
            let result = monitor.getDropResult();
            let pinned = !!result.raw?.pinned;
            console.log(result);

            component.setState({
                swapping: true
            }, () => {
                if (typeof result.selected?.value === 'undefined') { // misbehaving on empty cells
                    result = emptyMateriaChoice;
                    pinned = false;
                }

                component.onChange(result.selected).then(() => {
                    component.doCheckboxChange(pinned);
                });
            });

            setTimeout(() => {
                refreshRequestObservable.next(true);
            }, 500);
        }
    }
}, collectSource)(class extends React.Component<any, any> {
    constructor(props: any) {
        super(props);

        let val = 0;
        if (typeof this.props.data.materia !== 'undefined') {
            val = materiaMatch.filter((m: any) => m.value === this.props.data.materia.id)[0];
        }

        this.state = {
            editing: false,
            locked: false,
            errored: false,
            swapping: false,
            selected: val,
            raw: this.props.data
        }
    }

    onClick = () => {
        if (!this.state.editing) {
            this.setState({
                editing: true
            });
        }
    };

    onChange = (selected: any): Promise<void> => {
        let oldSelected = this.state.selected;
        this.setState({
            selected: selected,
            editing: true,
            locked: true,
            errored: false
        });

        return axios.default.patch(
            `/api/loadouts/${loadoutId}/items/${this.props.data.id}`,
            {materia: selected.value === 0 ? null : selected.value},
        )
            .then((res: any) => {
                this.setState({
                    errored: false,
                    locked: false,
                    editing: false,
                    swapping: false,
                    raw: res.data
                })
            })
            .catch(() => {
                this.setState({
                    errored: true,
                    locked: false,
                    swapping: false,
                    selected: oldSelected
                });
            });
    };

    onCheckboxChange = (evt: any): Promise<void> => {
        return this.doCheckboxChange(evt.target.checked);
    }

    private doCheckboxChange(pinned: boolean) {
        return axios.default.patch(
            `/api/loadouts/${loadoutId}/items/${this.props.data.id}`,
            {pinned: pinned},
        )
            .then((res: any) => {
                this.setState({
                    errored: false,
                    locked: false,
                    editing: false,
                    swapping: false,
                    raw: res.data
                })
            })
            .catch(() => {
                this.setState({
                    errored: true,
                    locked: false,
                    swapping: false,
                });
            });
    }

    render() {
        let content;

        if (this.state.editing) {
            const style = {
                option: (styles: any, {data, isDisabled, isFocused, isSelected}: any) => {
                    return {
                        ...styles,
                        backgroundColor: isSelected || isFocused
                            ? data.color
                            : null,
                        color: isSelected || isFocused
                            ? 'white'
                            : data.color
                    }
                }
            };

            content = <div className="col">
                <Select
                    value={this.state.selected}
                    onChange={this.onChange}
                    options={materiaChoices}
                    defaultMenuIsOpen={!this.state.swapping}
                    isDisabled={this.state.locked}
                    isLoading={this.state.locked}
                    autoFocus={!this.state.swapping}
                    styles={style}
                />
                <Form.Check
                    checked={!!this.state.raw.pinned}
                    onChange={this.onCheckboxChange}
                    disabled={this.state.locked}
                    label="Pinned"
                    type="checkbox"
                />
            </div>;
        } else {
            if (this.state.selected !== null) {
                const content1 = <div className="col">
                    <span className="materia-text">
                        {this.state.selected.label}
                    </span>
                </div>

                let content2;
                if (this.state.raw?.pinned === true) {
                    content2 = <div className="col-auto ml-auto">
                        <span className="materia-pinned">
                            <i className="fas fa-thumbtack" />
                        </span>
                    </div>
                }

                content = <React.Fragment>
                    {content1}
                    {content2}
                </React.Fragment>;
            }
        }

        return this.props.connectDropTarget(this.props.connectDragSource(<td
            className={['materia', `materia-${this.state.selected?.color}`].join(' ')}
            onClick={() => {
                this.onClick()
            }}>
            <div className="row no-gutters materia-row">
                <div className="col-auto">
                    <MateriaCoordinateDisplay character={this.props.data.charName} row={this.props.data.row} col={this.props.data.col} />
                    &nbsp;
                </div>
                {content}
            </div>
        </td>));
    }
}));

class LayoutRow extends React.Component<{ data: any, materias: any }, any> {
    render() {
        let cols = [];

        for (const item of this.props.data) {
            cols.push(<MateriaCell key={item.col} data={item} materias={this.props.materias} />);
        }

        return <tr>
            {cols}
        </tr>;
    }
}

class CharacterLayout extends React.Component<{ character: string, data: any, materias: any }, any> {
    render() {
        let items: any = {};

        for (const i of this.props.data.items) {
            if (i.charName == this.props.character) {
                if (typeof items[i.row] === 'undefined') {
                    items[i.row] = [];
                }

                items[i.row].push(i);
            }
        }

        return <div>
            <h3>Layout: {characters[this.props.character]}</h3>
            <table className="table table-bordered">
                <tbody>
                    <LayoutRow data={items[0]} materias={this.props.materias} />
                    <LayoutRow data={items[1]} materias={this.props.materias} />
                </tbody>
            </table>
        </div>
    }
}

class Table extends React.Component<{ id: any }, { data: any, materias: any }> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null,
            materias: null
        };
    }

    private doFetch() {
        axios.default.get(`/api/loadouts/${this.props.id}`)
            .then((res: any) => {
                axios.default.get('/api/materia')
                    .then((res2: any) => {
                        materiaChoices = [emptyMateriaChoice];
                        materiaMatch = [emptyMateriaChoice];

                        for (const group of res2.data) {
                            let options = [];

                            for (const materia of group.materias) {
                                let o = {
                                    value: materia.id,
                                    label: materia.name,
                                    color: group.color
                                };

                                options.push(o);
                                materiaMatch.push(o);
                            }

                            materiaChoices.push({
                                label: group.name,
                                color: group.color,
                                options: options
                            });
                        }

                        this.setState({
                            data: res.data,
                            materias: res2.data
                        });
                    })
            })
    }

    componentDidMount() {
        this.doFetch();
        loadoutId = this.props.id;
    }

    render() {
        if (null === this.state.data) {
            return <div>Loading</div>;
        }

        let layouts = [];
        let i = 0;

        for (const char of this.state.data.partyOrder) {
            layouts.push(
                <CharacterLayout key={i++} character={char} data={this.state.data} materias={this.state.materias} />)
        }

        return <div>
            <DndProvider backend={Backend}>
                {layouts}
            </DndProvider>
        </div>;
    }
}

class ChildLoadoutListRow extends React.Component<{ data: any, active: number, indent: number, onClick: any, lastInGroup?: boolean }, any> {
    render() {
        for (const k in completeIndentLevels) {
            if (completeIndentLevels.hasOwnProperty(k) && Number.parseInt(k) > this.props.indent) {
                completeIndentLevels.splice(k, 1);
            }
        }

        let i = 0;
        const children = this.props.data.children.map((e: any) => <ChildLoadoutListRow
            key={i++}
            data={e}
            active={this.props.active}
            indent={this.props.indent + 1}
            onClick={this.props.onClick}
            lastInGroup={i === this.props.data.children.length} />);

        let cc = ['clickable-row'];

        if (this.props.active === this.props.data.loadout.id) {
            cc.push('clickable-row-active');
        }

        // <span style={{paddingLeft: (this.props.indent * 20) + 'px', width: 0, display: 'inline-block'}}>&nbsp;</span>
        let indents = [];
        let j = 0;
        for (let i = 0; i < this.props.indent; i++) {
            if (typeof completeIndentLevels[i] !== 'undefined') {
                indents.push(<span key={j++} className={"indent indent-last"}>&nbsp;</span>)
                continue;
            }

            if (i === this.props.indent - 1) {
                if (this.props.lastInGroup === true) {
                    completeIndentLevels[this.props.indent - 1] = true;
                    indents.push(<span key={j++} className={"indent indent-last"}>&#x2514;</span>)
                } else {
                    indents.push(<span key={j++} className={"indent indent-last"}>&#x251C;</span>)
                }
            } else {
                indents.push(<span key={j++} className={"indent"}>&#x2502;</span>)
            }
        }

        return <div>
            <div onClick={() => {
                this.props.onClick(this.props.data.loadout)
            }} className={cc.join(' ')}>
                {indents}
                <span>
                    {this.props.data.loadout.name}
                </span>
            </div>
            {children}
        </div>;
    }
}

class ChildLoadoutList extends React.Component<{ baseId: number }, any> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null
        }
    }

    componentDidMount() {
        axios.default.get(`/api/loadouts/${this.props.baseId}/tree`)
            .then((res: any) => {
                if (res.data.root.id !== res.data.loadout.id) {
                    axios.default.get(`/api/loadouts/${res.data.root.id}/tree`)
                        .then((res: any) => {
                            this.setState({
                                data: res.data
                            });
                        });
                } else {
                    this.setState({
                        data: res.data
                    });
                }
            });
    }

    onSelect = (selected: any) => {
        window.location.href = `/view/${selected.id}`;
    }

    render() {
        if (this.state.data === null) {
            return <div>Loading</div>;
        }

        return <div id="child-loadout-list">
            <ChildLoadoutListRow
                data={this.state.data}
                active={this.props.baseId}
                indent={0}
                onClick={this.onSelect} />
        </div>
    }
}

class ChangeDisplaySolutionMoveSlots extends React.Component<{ move: any }, any> {
    render() {
        let from;
        if (this.props.move.from.charName !== 'i') {
            from = <MateriaCoordinateDisplay
                character={this.props.move.from.charName}
                row={this.props.move.from.row}
                col={this.props.move.from.col} />;
        }

        let to;
        if (this.props.move.to.charName !== 'i') {
            to = <MateriaCoordinateDisplay
                character={this.props.move.to.charName}
                row={this.props.move.to.row}
                col={this.props.move.to.col} />;
        }

        return <tr className="change-display-solution-move-slots">
            <td className="from name">
                {characters[this.props.move.from.charName]}
            </td>
            <td className="from slot">
                {from}
            </td>
            <td className="swap">&#x27fa;</td>
            <td className="to name">
                {characters[this.props.move.to.charName]}
            </td>
            <td className="to slot">
                {to}
            </td>
        </tr>;
    }
}

class ChangeDisplaySolutionMoveMateria extends React.Component<{ move: any }, any> {
    render() {
        return <tr className="change-display-solution-move-materia">
            <td className="from materia" style={{color: this.props.move.from.materia?.type?.color}} colSpan={2}>
                {this.props.move.from.materia?.name}
            </td>
            <td className="swap">&nbsp;</td>
            <td className="to materia" style={{color: this.props.move.to.materia?.type?.color}} colSpan={2}>
                {this.props.move.to.materia?.name}
            </td>
        </tr>;
    }
}

class ChangeDisplaySolutionMove extends React.Component<{ move: any }, any> {
    render() {
        return <React.Fragment>
            <ChangeDisplaySolutionMoveSlots move={this.props.move} />
            <ChangeDisplaySolutionMoveMateria move={this.props.move} />
        </React.Fragment>;
    }
}

class ChangeDisplaySolution extends React.Component<{ solution: any, favorite?: boolean }, any> {
    render() {
        let i = 0;
        let moves = this.props.solution.moves.map((m: any) => <ChangeDisplaySolutionMove key={i++} move={m} />);

        return <div className="change-display-solution">
            <table className="table">
                <tbody>
                    {moves}
                </tbody>
            </table>
        </div>
    }
}

class ChangeDisplay extends React.Component<{ loadoutId: number, favorite: string }, any> {
    private subscription: Subscription;

    constructor(props: any) {
        super(props);

        this.state = {
            data: null,
            errtext: false
        };
    }

    reload = () => {
        this.setState({
            data: null,
            errtext: false
        });

        axios.default.get(`/api/loadouts/${this.props.loadoutId}/diff`)
            .then((res: any) => {
                this.setState({
                    data: res.data
                });
            })
            .catch((reason: any) => {
                if (reason.response?.status === 400 && reason.response?.data?.error === 'no_parent') {
                    this.setState({
                        errtext: 'This is the parent, thus cannot have any changes. Click "Create changes from this loadout" to copy this loadout.'
                    })
                } else {
                    this.setState({
                        errtext: 'Something went wrong. Feel free to hit me up on Discord if you feel like it wasn\'t supposed to happen!'
                    })
                }
            });
    }

    componentDidMount() {
        this.reload();
        this.subscription = refreshRequestObservable.subscribe(() => {
            this.reload();
        });
    }

    componentWillUnmount() {
        this.subscription.unsubscribe();
    }

    render() {
        if (this.state.data === null) {
            if (this.state.errtext !== false) {
                return <div>{this.state.errtext}</div>;
            }

            return <div>Calculating changes...</div>;
        }

        let mb;
        if (this.state.data.allSolutions.length === 0) {
            mb = <div className="row mb-2">
                <div className="col">
                    No solution.
                </div>
                <div className="col-auto">
                    <Button onClick={() => {
                        this.reload()
                    }} size="sm">refresh</Button>
                </div>
            </div>;
        } else {
            let fav = null;
            if (this.props.favorite !== '') {
                for (const solution of this.state.data.allSolutions) {
                    if (solution.key === this.props.favorite) {
                        fav = solution;
                        break;
                    }
                }
            }

            mb = <div>
                {fav}
                <div className="row mb-2">
                    <div className="col">
                        {this.state.data.minimumDistance} D-pad presses
                    </div>
                    <div className="col-auto">
                        <Button onClick={() => {
                            this.reload()
                        }} size="sm">refresh</Button>
                    </div>
                </div>
                <div>
                    Starting from {characters[this.state.data.startingOn]}:
                </div>
                <div>
                    <ChangeDisplaySolution solution={this.state.data.matchingDistance[0]} />
                </div>
            </div>;
        }

        return <div>
            {mb}
        </div>;
    }
}

class Notes extends React.Component<any, any> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null
        }
    }

    componentDidMount() {
        axios.default.get(`/api/loadouts/${this.props.loadoutId}/notes`)
            .then((res: any) => {
                this.setState({
                    data: res.data,
                });
            });
    }

    render() {
        if (this.state.data === null) {
            return <div>Loading</div>;
        }

        return <div dangerouslySetInnerHTML={{__html: marked(this.state.data?.notes || '')}}></div>
    }
}

let table = document.getElementById('table');

class LoadoutSidebar extends React.Component<any, any> {
    render() {
        let loadoutId = Number.parseInt(table.getAttribute('data-id'));
        let fav = document.getElementById('sidebar').getAttribute('data-fav')

        return <Accordion defaultActiveKey="2">
            <Card>
                <Card.Header>
                    <Accordion.Toggle as={Button} variant="link" eventKey="0">
                        Navigation
                    </Accordion.Toggle>
                </Card.Header>
                <Accordion.Collapse eventKey="0">
                    <Card.Body>
                        <ChildLoadoutList baseId={loadoutId} />
                    </Card.Body>
                </Accordion.Collapse>
            </Card>
            <Card>
                <Card.Header>
                    <Accordion.Toggle as={Button} variant="link" eventKey="1">
                        Notes
                    </Accordion.Toggle>
                </Card.Header>
                <Accordion.Collapse eventKey="1">
                    <Card.Body>
                        <Notes loadoutId={loadoutId} />
                    </Card.Body>
                </Accordion.Collapse>
            </Card>
            <Card>
                <Card.Header>
                    <Accordion.Toggle as={Button} variant="link" eventKey="2">
                        Materia changelist
                    </Accordion.Toggle>
                </Card.Header>
                <Accordion.Collapse eventKey="2">
                    <Card.Body>
                        <ChangeDisplay loadoutId={loadoutId} favorite={fav} />
                    </Card.Body>
                </Accordion.Collapse>
            </Card>
        </Accordion>;
    }
}

class RDRLShortMoveDisplay extends React.Component<any, any> {
    render() {
        return <span>
            {characters[this.props.move.from.charName]}&nbsp;
            <MateriaCoordinateDisplay
                character={this.props.move.from.charName}
                row={this.props.move.from.row}
                col={this.props.move.from.col} />&nbsp;
            (
            <span style={{color: this.props.move.from.materia?.type?.color}}>
                {this.props.move.from.materia?.name}
            </span>
            )
            &#x27fa;&nbsp;
            {characters[this.props.move.to.charName]}&nbsp;
            <MateriaCoordinateDisplay
                character={this.props.move.to.charName}
                row={this.props.move.to.row}
                col={this.props.move.to.col} />&nbsp;
            (
            <span style={{color: this.props.move.to.materia?.type?.color}}>
                {this.props.move.to.materia?.name}
            </span>
            )
        </span>
    }
}

class RecursiveDiffResolverLeaf extends React.Component<any, any> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null,
            tree: null,
            errored: false
        }
    }

    componentDidMount() {
        axios.default.get(
            `/api/loadouts/diff/${this.props.pid}/${this.props.id}`,
        )
            .then((res: any) => {
                this.setState({
                    data: res.data
                });

                this.props.eotcb();

                axios.default.get(
                    `/api/loadouts/${this.props.id}/tree`,
                )
                    .then((res: any) => {
                        this.setState({
                            tree: res.data
                        });
                    })
                    .catch(() => {
                        this.setState({
                            data: null,
                            errored: true
                        });
                    });
            })
            .catch(() => {
                this.setState({
                    errored: true
                });
            });
    }

    render() {
        if (this.state.errored) {
            return <div>Something went wrong.</div>;
        }

        if (this.state.data === null || this.state.tree === null) {
            return <div>Loading</div>;
        }

        const found = this.state.data.solution

        let solution = <div style={{color: 'red', paddingLeft: '20px'}}>No solution.</div>
        let next;

        if (found) {
            let i = 0;
            solution = <div style={{paddingLeft: '20px'}}>
                <div>({found.distance} D-pad presses)</div>
                {found.moves.map((m: any) => <div key={i++}>
                    <RDRLShortMoveDisplay
                        move={m} />
                </div>)}
            </div>

            next = this.state.tree.children.map((m: any) => <RecursiveDiffResolverLeaf
                key={m.loadout.id}
                pid={this.state.data.cacheId}
                id={m.loadout.id}
                eotcb={this.props.eotcb} />);
        }

        return <div>
            <div>
                <b>{this.state.tree.loadout.name}</b>{solution}
            </div>
            <div>
                {next}
            </div>
        </div>;
    }
}

class RecursiveDiffResolver extends React.Component<any, any> {
    constructor(props: any) {
        super(props);

        this.state = {
            data: null,
            tree: null,
            finished: 0,
            target: 0,
            saving: false,
            errored: false
        };
    }

    componentDidMount() {
        axios.default.get(
            `/api/loadouts/diff/${this.props.id}/0`,
        )
            .then((res: any) => {
                this.setState({
                    data: res.data,
                    target: res.data.target,
                    finished: 1 //self
                });

                axios.default.get(
                    `/api/loadouts/${this.props.id}/tree`,
                )
                    .then((res: any) => {
                        this.setState({
                            tree: res.data
                        });
                    })
                    .catch((e: any) => {
                        console.log(e);
                        this.setState({
                            data: null,
                            errored: true
                        });
                    });
            })
            .catch(() => {
                this.setState({
                    errored: true
                });
            });
    }

    onFinishedRender = () => {
        this.setState({
            finished: this.state.finished + 1
        });
    }

    onFinishedButtonClick = () => {
        if (!this.state.saving) {
            this.setState({
                saving: true
            });

            axios.default.post(
                `/api/loadouts/diff/to_loadout/${this.state.data.cacheId}`,
            )
                .then((res: any) => {
                    window.location.href = `/view/${res.data.id}`;
                })
                .catch(() => {
                    this.setState({
                        errored: true
                    });
                });
        }
    }

    render() {
        if (this.state.errored) {
            return <div>Something went wrong.</div>;
        }

        if (this.state.data === null || this.state.tree === null) {
            return <div>Loading</div>;
        }

        let children = this.state.tree.children.map((m: any) => <RecursiveDiffResolverLeaf
            key={m.loadout.id}
            pid={this.state.data.cacheId}
            id={m.loadout.id}
            eotcb={this.onFinishedRender} />);

        let finished;
        if (this.state.finished === this.state.target) {
            finished = <div>
                <Button
                    type="button"
                    variant="primary"
                    onClick={this.onFinishedButtonClick}
                    disabled={this.state.saving}>
                    Save this calculation as a new base loadout
                </Button>
            </div>
        }

        return <div>
            <div>
                <b>{this.state.tree.loadout.name}</b> (base)
            </div>
            <div>
                {children}
            </div>
            {finished}
        </div>;
    }
}

if (table) {
    ReactDOM.render(
        <Table id={table.getAttribute('data-id')} />,
        table
    );

    ReactDOM.render(
        <LoadoutSidebar />,
        document.getElementById('sidebar')
    );
}

let display = document.getElementById('display');
if (display) {
    ReactDOM.render(
        <RecursiveDiffResolver id={display.getAttribute('data-id')} />,
        display
    )
}